<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * EmailService
 *
 * Lightweight, zero-dependency email service supporting:
 * 1. Direct SMTP with TLS/SSL via stream sockets (supports Gmail, Brevo, SendGrid, Mailgun, AWS SES, etc.)
 * 2. PHP native mail() fallback
 * 3. File logging in runtime/logs/email.log for local testing and audit trail
 * 4. Rich, responsive HTML templates for job application notifications
 */
class EmailService
{
    private string $mailer;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private string $logPath;
    /** @var callable|null */
    private $transport;

    private function getEnvVar(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string)$_SERVER[$key];
        }
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return (string)$val;
        }
        return $default;
    }

    public function __construct(array $config = [])
    {
        $this->host = trim((string)($config['host'] ?? $this->getEnvVar('MAIL_HOST') ?: $this->getEnvVar('SMTP_HOST') ?: ''));
        $this->port = (int)($config['port'] ?? $this->getEnvVar('MAIL_PORT') ?: $this->getEnvVar('SMTP_PORT') ?: 587);
        $this->username = trim((string)($config['username'] ?? $this->getEnvVar('MAIL_USERNAME') ?: $this->getEnvVar('SMTP_USER') ?: ''));
        $this->password = (string)($config['password'] ?? $this->getEnvVar('MAIL_PASSWORD') ?: $this->getEnvVar('SMTP_PASS') ?: '');
        $this->encryption = strtolower(trim((string)($config['encryption'] ?? $this->getEnvVar('MAIL_ENCRYPTION') ?: $this->getEnvVar('SMTP_CRYPTO') ?: 'tls')));

        $defaultFrom = $this->getEnvVar('MAIL_FROM_ADDRESS') ?: 'noreply@omniroute.local';
        $defaultName = $this->getEnvVar('MAIL_FROM_NAME') ?: (function_exists('config_item') ? config_item('app_name') : 'Dispatch Platform');

        $this->fromAddress = trim((string)($config['from_address'] ?? $defaultFrom));
        $this->fromName = trim((string)($config['from_name'] ?? $defaultName));

        $defaultMailer = $this->host !== '' ? 'smtp' : (PHP_OS_FAMILY === 'Windows' ? 'log' : 'mail');
        $this->mailer = strtolower(trim((string)($config['mailer'] ?? $this->getEnvVar('MAIL_MAILER') ?: $defaultMailer)));
        $this->logPath = (defined('RUNTIME_DIR') ? RUNTIME_DIR : (defined('ROOT_DIR') ? ROOT_DIR . 'runtime/' : __DIR__ . '/../../runtime/')) . 'logs/email.log';
        $this->transport = isset($config['transport']) && is_callable($config['transport']) ? $config['transport'] : null;
    }

    public function isEnabled(): bool
    {
        $enabledEnv = $this->getEnvVar('MAIL_ENABLED');
        if ($enabledEnv !== '') {
            return $enabledEnv === '1' || strtolower($enabledEnv) === 'true';
        }
        return true;
    }

    public function getMailer(): string
    {
        return $this->mailer;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    /**
     * Send an email with HTML and optional plain text content.
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid recipient email address: {$to}"];
        }

        $subject = trim($subject);
        if ($subject === '') {
            return ['success' => false, 'error' => 'Email subject cannot be empty.'];
        }

        if ($textBody === '') {
            $textBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
        }

        // 0. Check if email sending is disabled globally via MAIL_ENABLED=0
        if (!$this->isEnabled()) {
            $result = [
                'success' => true,
                'message' => 'Email delivery is disabled (MAIL_ENABLED=0); logged to runtime/logs/email.log.',
                'simulated' => true
            ];
            $this->logDispatch($to, $subject, $htmlBody, $result);
            return $result;
        }

        // If configured for 'log' mailer, record to log without attempting network transport
        if ($this->mailer === 'log') {
            $result = [
                'success' => true,
                'message' => 'Email logged to runtime/logs/email.log (MAIL_MAILER=log).',
                'simulated' => true
            ];
            $this->logDispatch($to, $subject, $htmlBody, $result);
            return $result;
        }

        // Custom transport for testing/mocking
        if ($this->transport !== null && is_callable($this->transport)) {
            $headers = [
                'From' => "{$this->fromName} <{$this->fromAddress}>",
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8'
            ];
            $sent = ($this->transport)($to, $subject, $htmlBody, $headers, $textBody);
            $result = ['success' => (bool)$sent, 'message' => $sent ? 'Sent via mock transport' : 'Mock transport rejected'];
            $this->logDispatch($to, $subject, $htmlBody, $result);
            return $result;
        }

        $result = ['success' => false, 'error' => ''];

        // 1. If SMTP is configured, attempt SMTP delivery first
        if ($this->mailer === 'smtp' && $this->host !== '') {
            try {
                $this->sendViaSmtp($to, $subject, $htmlBody, $textBody);
                $result = ['success' => true, 'message' => 'Sent via SMTP'];
            } catch (Throwable $e) {
                $smtpError = $e->getMessage();
                // Attempt fallback to native mail()
                try {
                    $mailSent = $this->sendViaNativeMail($to, $subject, $htmlBody, $textBody);
                    if ($mailSent) {
                        $result = ['success' => true, 'message' => 'Sent via native mail fallback (SMTP failed: ' . $smtpError . ')'];
                    } else {
                        $result = ['success' => false, 'error' => 'SMTP failed: ' . $smtpError . '; PHP mail() returned false'];
                    }
                } catch (Throwable $ex) {
                    $result = ['success' => false, 'error' => 'SMTP failed: ' . $smtpError . '; Fallback error: ' . $ex->getMessage()];
                }
            }
        } elseif ($this->mailer === 'smtp' && $this->host === '') {
            $result = ['success' => false, 'error' => 'SMTP mailer is configured but MAIL_HOST is empty in .env.'];
        } else {
            // 2. Use PHP native mail()
            try {
                $mailSent = $this->sendViaNativeMail($to, $subject, $htmlBody, $textBody);
                $result = $mailSent
                    ? ['success' => true, 'message' => 'Sent via PHP mail()']
                    : ['success' => false, 'error' => 'PHP mail() function returned false (MTA may not be configured)'];
            } catch (Throwable $e) {
                $result = ['success' => false, 'error' => 'PHP mail() error: ' . $e->getMessage()];
            }
        }

        // Always log the email dispatch to runtime/logs/email.log for audit and debugging
        $this->logDispatch($to, $subject, $htmlBody, $result);

        return $result;
    }

    /**
     * Send application approval notification email.
     */
    public function sendApplicationApproval(array $application, string $temporaryPassword = '', bool $isNewAccount = true): array
    {
        $applicantName = htmlspecialchars($application['full_name'] ?? 'Applicant', ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($application['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $roleApplied = ucfirst(htmlspecialchars($application['role_applied'] ?? 'Rider', ENT_QUOTES, 'UTF-8'));
        $appName = htmlspecialchars($this->fromName ?: 'Dispatch Platform', ENT_QUOTES, 'UTF-8');

        $loginUrl = function_exists('base_url') ? base_url('login') : 'http://localhost/SIAproject/login';
        $adminNote = trim((string)($application['admin_note'] ?? ''));

        $subject = "Congratulations! Your {$roleApplied} Application Has Been Approved - {$appName}";

        $credentialBox = '';
        if ($isNewAccount && $temporaryPassword !== '') {
            $safePass = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
            $credentialBox = <<<HTML
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 18px 20px; margin: 24px 0;">
                <h3 style="margin-top: 0; color: #166534; font-size: 16px; font-weight: 600;">Your Account Login Credentials</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px; color: #1e293b;">
                    <tr>
                        <td style="padding: 6px 0; width: 130px; font-weight: 600; color: #475569;">Login Portal:</td>
                        <td style="padding: 6px 0;"><a href="{$loginUrl}" style="color: #0284c7; text-decoration: underline;">{$loginUrl}</a></td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-weight: 600; color: #475569;">Email Address:</td>
                        <td style="padding: 6px 0; font-family: monospace; font-size: 15px; color: #0f172a;">{$email}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-weight: 600; color: #475569;">Temporary Password:</td>
                        <td style="padding: 6px 0;"><code style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-size: 15px; font-weight: bold; color: #0f172a; letter-spacing: 0.5px;">{$safePass}</code></td>
                    </tr>
                </table>
                <p style="margin: 12px 0 0 0; font-size: 12px; color: #15803d;">
                    <em>Security notice: Please log in and change your temporary password immediately from your profile settings.</em>
                </p>
            </div>
HTML;
        } else {
            $credentialBox = <<<HTML
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin: 24px 0;">
                <p style="margin: 0; font-size: 14px; color: #334155;">
                    Your account has been linked. You can sign in using your existing account credentials at: <br>
                    <a href="{$loginUrl}" style="color: #0284c7; font-weight: 600; text-decoration: underline;">{$loginUrl}</a>
                </p>
            </div>
HTML;
        }

        $noteSection = '';
        if ($adminNote !== '') {
            $safeNote = nl2br(htmlspecialchars($adminNote, ENT_QUOTES, 'UTF-8'));
            $noteSection = <<<HTML
            <div style="margin: 20px 0; padding: 14px 18px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 4px;">
                <strong style="color: #b45309; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Reviewer Note:</strong>
                <p style="margin: 6px 0 0 0; color: #78350f; font-size: 14px;">{$safeNote}</p>
            </div>
HTML;
        }

        $roleSpecificGuidance = '';
        if (strtolower($application['role_applied'] ?? '') === 'rider') {
            $roleSpecificGuidance = <<<HTML
            <div style="margin: 20px 0; font-size: 14px; line-height: 1.6; color: #334155;">
                <h4 style="margin: 0 0 8px 0; color: #0f172a; font-size: 15px;">Next steps for riders:</h4>
                <ul style="margin: 0 0 16px 0; padding-left: 22px; color: #475569;">
                    <li>Your rider profile has been registered with initial status: <strong>Available</strong>.</li>
                    <li>If you have not registered your permanent vehicle plate number, please coordinate with dispatch or update your profile.</li>
                    <li>Access your delivery dashboard to view active and upcoming dispatch orders.</li>
                </ul>
            </div>
HTML;
        } else {
            $roleSpecificGuidance = <<<HTML
            <div style="margin: 20px 0; font-size: 14px; line-height: 1.6; color: #334155;">
                <h4 style="margin: 0 0 8px 0; color: #0f172a; font-size: 15px;">Next steps for dispatchers:</h4>
                <ul style="margin: 0 0 16px 0; padding-left: 22px; color: #475569;">
                    <li>Log in to access the order dispatch board and fleet monitoring tools.</li>
                    <li>Review active delivery routes, customer orders, and internal rider availability.</li>
                </ul>
            </div>
HTML;
        }

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application Approved</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 28px 36px; text-align: left;">
                            <span style="font-size: 22px; font-weight: 700; color: #38bdf8; letter-spacing: -0.5px;">Dispatch</span>
                            <span style="font-size: 14px; color: #94a3b8; margin-left: 8px;">Logistics & Fleet</span>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 36px 36px 24px 36px;">
                            <div style="display: inline-block; background-color: #dcfce7; color: #15803d; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px;">
                                Application Approved
                            </div>
                            <h1 style="margin: 0 0 16px 0; font-size: 24px; font-weight: 700; color: #0f172a;">
                                Welcome to the team, {$applicantName}!
                            </h1>
                            <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #334155;">
                                Great news! After reviewing your credentials and ID submission, our administration team has approved your application for the <strong>{$roleApplied}</strong> role.
                            </p>

                            {$credentialBox}
                            {$noteSection}
                            {$roleSpecificGuidance}

                            <div style="text-align: center; margin: 32px 0 16px 0;">
                                <a href="{$loginUrl}" style="background-color: #0284c7; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 12px 32px; border-radius: 6px; display: inline-block;">
                                    Sign In to Portal
                                </a>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 36px; text-align: center; font-size: 13px; color: #64748b;">
                            <p style="margin: 0 0 4px 0;">{$appName} &bull; Delivery & Logistics Management System</p>
                            <p style="margin: 0; font-size: 12px; color: #94a3b8;">This is an automated notification. Please do not reply directly to this email.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        return $this->send($application['email'], $subject, $htmlBody);
    }

    /**
     * Send application rejection notification email.
     */
    public function sendApplicationRejection(array $application, string $adminNote = ''): array
    {
        $applicantName = htmlspecialchars($application['full_name'] ?? 'Applicant', ENT_QUOTES, 'UTF-8');
        $roleApplied = ucfirst(htmlspecialchars($application['role_applied'] ?? 'position', ENT_QUOTES, 'UTF-8'));
        $appName = htmlspecialchars($this->fromName ?: 'Dispatch Platform', ENT_QUOTES, 'UTF-8');

        $subject = "Update regarding your application for {$roleApplied} - {$appName}";

        $noteSection = '';
        if (trim($adminNote) !== '') {
            $safeNote = nl2br(htmlspecialchars($adminNote, ENT_QUOTES, 'UTF-8'));
            $noteSection = <<<HTML
            <div style="margin: 20px 0; padding: 14px 18px; background: #f8fafc; border-left: 4px solid #94a3b8; border-radius: 4px;">
                <strong style="color: #475569; font-size: 13px;">Feedback from recruiting:</strong>
                <p style="margin: 6px 0 0 0; color: #334155; font-size: 14px;">{$safeNote}</p>
            </div>
HTML;
        }

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application Update</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #0f172a; padding: 24px 36px; text-align: left;">
                            <span style="font-size: 20px; font-weight: 700; color: #38bdf8;">Dispatch</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px;">
                            <h2 style="margin: 0 0 16px 0; font-size: 20px; color: #0f172a;">Hello {$applicantName},</h2>
                            <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #334155;">
                                Thank you for your interest in joining the Dispatch team as a <strong>{$roleApplied}</strong>.
                            </p>
                            <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #334155;">
                                After careful consideration of your application and current fleet capacity, we are unable to move forward with your application at this time.
                            </p>
                            {$noteSection}
                            <p style="margin: 24px 0 0 0; font-size: 14px; color: #64748b;">
                                We appreciate the time you took to apply and wish you the best in your career pursuits.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 18px 36px; text-align: center; font-size: 12px; color: #94a3b8;">
                            {$appName} &bull; Delivery & Logistics Management System
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        return $this->send($application['email'], $subject, $htmlBody);
    }

    /**
     * Render a modern, responsive Omniroute Dispatch HTML email template.
     */
    public function renderEmailTemplate(
        string $title,
        string $badgeText,
        string $badgeBg,
        string $badgeColor,
        string $headline,
        string $messageHtml,
        array $detailsRows = [],
        ?string $actionUrl = null,
        ?string $actionText = null
    ): string {
        $appName = htmlspecialchars($this->fromName ?: 'Omniroute Dispatch', ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeBadge = htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8');
        $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');

        $rowsHtml = '';
        if (!empty($detailsRows)) {
            $rowsHtml .= '<div style="margin: 24px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px;">';
            $rowsHtml .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px; border-collapse: collapse;">';
            foreach ($detailsRows as $label => $value) {
                if ($value === null || $value === '') continue;
                $safeLabel = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
                $safeVal = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                $rowsHtml .= '<tr>';
                $rowsHtml .= '<td style="padding: 6px 0; width: 140px; font-weight: 600; color: #64748b;">' . $safeLabel . ':</td>';
                $rowsHtml .= '<td style="padding: 6px 0; color: #0f172a; font-weight: 500;">' . $safeVal . '</td>';
                $rowsHtml .= '</tr>';
            }
            $rowsHtml .= '</table></div>';
        }

        $buttonHtml = '';
        if ($actionUrl && $actionText) {
            $safeUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
            $safeBtnText = htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8');
            $buttonHtml = <<<HTML
            <div style="margin: 28px 0 12px 0; text-align: left;">
                <a href="{$safeUrl}" style="display: inline-block; background-color: #0284c7; color: #ffffff; text-decoration: none; padding: 12px 24px; font-size: 14px; font-weight: 600; border-radius: 6px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
                    {$safeBtnText} &rarr;
                </a>
            </div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeTitle}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.07); border: 1px solid #e2e8f0;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 22px 32px; text-align: left;">
                            <span style="font-size: 20px; font-weight: 700; color: #38bdf8; letter-spacing: -0.5px;">Omniroute</span>
                            <span style="font-size: 13px; color: #94a3b8; margin-left: 8px;">Delivery &amp; Logistics</span>
                        </td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding: 32px;">
                            <div style="display: inline-block; background-color: {$badgeBg}; color: {$badgeColor}; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 16px;">
                                {$safeBadge}
                            </div>
                            <h1 style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                {$safeHeadline}
                            </h1>
                            <div style="font-size: 15px; line-height: 1.6; color: #334155;">
                                {$messageHtml}
                            </div>
                            {$rowsHtml}
                            {$buttonHtml}
                            <p style="margin: 28px 0 0 0; font-size: 13px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                                Have questions? Contact dispatch support or view your order dashboard for live status.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 18px 32px; text-align: center; font-size: 12px; color: #94a3b8;">
                            {$appName} &bull; Automated Delivery Notification System
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Send delivery status transition email notification.
     */
    public function sendDeliveryStatusNotification(
        string $to,
        string $recipientName,
        string $orderNo,
        string $status,
        string $statusMessage,
        array $details = []
    ): array {
        $appName = htmlspecialchars($this->fromName ?: 'Omniroute Dispatch', ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($recipientName ?: 'Valued Customer', ENT_QUOTES, 'UTF-8');
        $safeOrderNo = htmlspecialchars($orderNo ?: 'Order', ENT_QUOTES, 'UTF-8');
        $orderId = (int)($details['order_id'] ?? 0);
        $orderUrl = $orderId > 0 && function_exists('base_url') ? base_url('orders/' . $orderId) : null;

        $configs = [
            'rider_assigned' => [
                'subject' => "[{$safeOrderNo}] Rider Assigned - On the Way for Pickup",
                'badge' => 'Rider Assigned',
                'badgeBg' => '#e0f2fe',
                'badgeColor' => '#0369a1',
                'headline' => "A Rider Has Been Assigned to Order {$safeOrderNo}",
                'lead' => "Hello {$safeName},<br><br>Good news! A delivery rider has been assigned to your order and is currently en route to the pickup location.",
                'btnText' => 'Track Your Delivery'
            ],
            'picked_up' => [
                'subject' => "[{$safeOrderNo}] Package Picked Up - In Transit",
                'badge' => 'In Transit',
                'badgeBg' => '#fef3c7',
                'badgeColor' => '#b45309',
                'headline' => "Your Order {$safeOrderNo} Is on the Way!",
                'lead' => "Hello {$safeName},<br><br>Your package has been collected by the rider and is now in transit to the delivery destination.",
                'btnText' => 'Track Live Delivery'
            ],
            'delivered' => [
                'subject' => "[{$safeOrderNo}] Delivered Successfully",
                'badge' => 'Delivered',
                'badgeBg' => '#dcfce7',
                'badgeColor' => '#15803d',
                'headline' => "Package Delivered Successfully!",
                'lead' => "Hello {$safeName},<br><br>Your delivery order {$safeOrderNo} has been delivered. Thank you for using {$appName}!",
                'btnText' => 'View Delivery Proof & Details'
            ],
            'cancelled' => [
                'subject' => "[{$safeOrderNo}] Delivery Order Cancelled",
                'badge' => 'Cancelled',
                'badgeBg' => '#fee2e2',
                'badgeColor' => '#b91c1c',
                'headline' => "Order {$safeOrderNo} Has Been Cancelled",
                'lead' => "Hello {$safeName},<br><br>Your delivery order {$safeOrderNo} was cancelled. If you have questions or need to place a new request, please coordinate with dispatch.",
                'btnText' => 'View Order'
            ],
            'failed' => [
                'subject' => "[{$safeOrderNo}] Delivery Issue Alert",
                'badge' => 'Delivery Issue',
                'badgeBg' => '#fee2e2',
                'badgeColor' => '#991b1b',
                'headline' => "Issue With Delivery Order {$safeOrderNo}",
                'lead' => "Hello {$safeName},<br><br>An issue was encountered while attempting to deliver order {$safeOrderNo}. Our support and dispatch team has been notified.",
                'btnText' => 'Check Order Status'
            ],
        ];

        $cfg = $configs[$status] ?? [
            'subject' => "[{$safeOrderNo}] Status Update: {$status}",
            'badge' => ucfirst(str_replace('_', ' ', $status)),
            'badgeBg' => '#f1f5f9',
            'badgeColor' => '#334155',
            'headline' => "Update on Order {$safeOrderNo}",
            'lead' => "Hello {$safeName},<br><br>" . htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'),
            'btnText' => 'View Order Details'
        ];

        $detailsRows = [
            'Order Number' => $orderNo,
            'Status' => ucfirst(str_replace('_', ' ', $status)),
        ];
        if (!empty($details['rider_name'])) {
            $detailsRows['Assigned Rider'] = $details['rider_name'] . (!empty($details['rider_phone']) ? ' (' . $details['rider_phone'] . ')' : '');
        }
        if (!empty($details['pickup_address'])) {
            $detailsRows['Pickup Address'] = $details['pickup_address'];
        }
        if (!empty($details['delivery_address'])) {
            $detailsRows['Delivery Address'] = $details['delivery_address'];
        }

        $htmlBody = $this->renderEmailTemplate(
            $cfg['subject'],
            $cfg['badge'],
            $cfg['badgeBg'],
            $cfg['badgeColor'],
            $cfg['headline'],
            $cfg['lead'],
            $detailsRows,
            $orderUrl,
            $cfg['btnText']
        );

        $textBody = "{$cfg['headline']}\n\n" . strip_tags($cfg['lead']) . "\n\nOrder: {$orderNo}\nStatus: {$status}\n";
        if ($orderUrl) {
            $textBody .= "Track: {$orderUrl}\n";
        }

        return $this->send($to, $cfg['subject'], $htmlBody, $textBody);
    }

    /**
     * Send payment confirmation email notification.
     */
    public function sendPaymentConfirmation(
        string $to,
        string $recipientName,
        string $orderNo,
        string $amount,
        string $paymentMethod = 'Online Checkout',
        array $details = []
    ): array {
        $safeName = htmlspecialchars($recipientName ?: 'Valued Customer', ENT_QUOTES, 'UTF-8');
        $safeOrderNo = htmlspecialchars($orderNo ?: 'Order', ENT_QUOTES, 'UTF-8');
        $formattedAmount = number_format((float)$amount, 2);
        $orderId = (int)($details['order_id'] ?? 0);
        $orderUrl = $orderId > 0 && function_exists('base_url') ? base_url('orders/' . $orderId) : null;

        $subject = "[{$safeOrderNo}] Payment Confirmed - Omniroute Delivery";
        $headline = "Payment Confirmed for Order {$safeOrderNo}";
        $lead = "Hello {$safeName},<br><br>Thank you for your payment! We have successfully confirmed your payment of <strong>PHP {$formattedAmount}</strong> for delivery order {$safeOrderNo}. Your order has been marked as paid and is queued for rider dispatch.";

        $detailsRows = [
            'Order Number' => $orderNo,
            'Amount Paid' => 'PHP ' . $formattedAmount,
            'Payment Method' => $paymentMethod,
            'Payment Date' => date('F j, Y, g:i a'),
            'Payment Status' => 'Paid'
        ];

        $htmlBody = $this->renderEmailTemplate(
            $subject,
            'Payment Received',
            '#dcfce7',
            '#15803d',
            $headline,
            $lead,
            $detailsRows,
            $orderUrl,
            'View Order & Dispatch Status'
        );

        $textBody = "{$headline}\n\nThank you! Payment of PHP {$formattedAmount} for order {$orderNo} via {$paymentMethod} is confirmed.\n";
        if ($orderUrl) {
            $textBody .= "View order: {$orderUrl}\n";
        }

        return $this->send($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Send a test notification email.
     */
    public function sendTestNotification(string $to, string $recipientName = ''): array
    {
        $safeName = htmlspecialchars($recipientName ?: 'User', ENT_QUOTES, 'UTF-8');
        $subject = 'Omniroute Delivery Notification - System Test';
        $headline = 'Email Notifications Operational';
        $lead = "Hello {$safeName},<br><br>This is a test notification confirming that email notifications are active and delivering properly to your inbox. You will receive updates here for payment confirmations and key delivery milestones.";

        $detailsRows = [
            'Service' => 'Omniroute Dispatch Notifications',
            'Transport' => strtoupper($this->mailer) . ($this->mailer === 'smtp' && $this->host ? " ({$this->host}:{$this->port})" : ''),
            'Sender' => $this->fromAddress,
            'Timestamp' => date('Y-m-d H:i:s T'),
            'Status' => 'Active'
        ];

        $portalUrl = function_exists('base_url') ? base_url('orders') : null;

        $htmlBody = $this->renderEmailTemplate(
            $subject,
            'Test Notification',
            '#e0e7ff',
            '#4338ca',
            $headline,
            $lead,
            $detailsRows,
            $portalUrl,
            'Go to Orders Dashboard'
        );

        $textBody = "{$headline}\n\nEmail notification test dispatched successfully at " . date('Y-m-d H:i:s') . ".\n";

        return $this->send($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Send via direct SMTP socket.
     */
    private function sendViaSmtp(string $to, string $subject, string $htmlBody, string $textBody): void
    {
        $prefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $remote = $prefix . $this->host . ':' . $this->port;

        $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server ({$remote}): [{$errno}] {$errstr}");
        }

        stream_set_timeout($socket, 10);

        $this->readSmtpResponse($socket, 220);

        // EHLO
        $clientHost = gethostname() ?: 'localhost';
        $this->writeSmtpCommand($socket, "EHLO {$clientHost}", 250);

        // STARTTLS if required and port 587 or encryption=tls
        if ($this->encryption === 'tls') {
            $this->writeSmtpCommand($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Failed to establish TLS encryption with SMTP server.');
            }
            $this->writeSmtpCommand($socket, "EHLO {$clientHost}", 250);
        }

        // Authentication if credentials provided
        if ($this->username !== '') {
            $this->writeSmtpCommand($socket, 'AUTH LOGIN', 334);
            $this->writeSmtpCommand($socket, base64_encode($this->username), 334);
            $this->writeSmtpCommand($socket, base64_encode($this->password), 235);
        }

        // MAIL FROM
        $this->writeSmtpCommand($socket, "MAIL FROM:<{$this->fromAddress}>", 250);

        // RCPT TO
        $this->writeSmtpCommand($socket, "RCPT TO:<{$to}>", 250);

        // DATA
        $this->writeSmtpCommand($socket, 'DATA', 354);

        $boundary = 'boundary_' . md5(uniqid((string)mt_rand(), true));
        $eol = "\r\n";

        $domain = !empty($this->fromAddress) && str_contains($this->fromAddress, '@')
            ? substr(strrchr($this->fromAddress, '@'), 1)
            : 'gmail.com';
        $messageId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(16)), time(), $domain);

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . ($this->fromName ? '=?UTF-8?B?' . base64_encode($this->fromName) . "?= <{$this->fromAddress}>" : $this->fromAddress);
        $headers[] = "Reply-To: <{$this->fromAddress}>";
        $headers[] = "To: <{$to}>";
        $headers[] = "Message-ID: {$messageId}";
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = 'X-Mailer: Dispatch-Mailer/1.0';

        $body  = implode($eol, $headers) . $eol . $eol;
        $body .= "--{$boundary}" . $eol;
        $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($textBody), 76, $eol);
        $body .= "--{$boundary}" . $eol;
        $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($htmlBody), 76, $eol);
        $body .= "--{$boundary}--" . $eol;
        $body .= '.' . $eol;

        fwrite($socket, $body);
        $this->readSmtpResponse($socket, 250);

        // QUIT
        fwrite($socket, 'QUIT' . $eol);
        fclose($socket);
    }

    private function writeSmtpCommand($socket, string $command, int $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->readSmtpResponse($socket, $expectedCode);
    }

    private function readSmtpResponse($socket, int $expectedCode): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // Check if end of response (code followed by space, not hyphen)
            if (preg_match('/^[0-9]{3} /', $line)) {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SMTP error [expected {$expectedCode}, got {$code}]: " . trim($response));
        }
        return $response;
    }

    /**
     * Send via PHP native mail()
     */
    private function sendViaNativeMail(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        $boundary = 'boundary_' . md5(uniqid((string)mt_rand(), true));
        $eol = "\r\n";

        $headers = [];
        $domain = !empty($this->fromAddress) && str_contains($this->fromAddress, '@')
            ? substr(strrchr($this->fromAddress, '@'), 1)
            : 'gmail.com';
        $messageId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(16)), time(), $domain);
        $from = $this->fromName ? '=?UTF-8?B?' . base64_encode($this->fromName) . "?= <{$this->fromAddress}>" : $this->fromAddress;
        $headers[] = "From: {$from}";
        $headers[] = "Reply-To: {$this->fromAddress}";
        $headers[] = "Message-ID: {$messageId}";
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = 'X-Mailer: Dispatch-Mailer/1.0';

        $body  = "--{$boundary}" . $eol;
        $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($textBody), 76, $eol);
        $body .= "--{$boundary}" . $eol;
        $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($htmlBody), 76, $eol);
        $body .= "--{$boundary}--";

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $extraParams = '-f' . escapeshellarg($this->fromAddress);

        $sent = false;
        // Suppress native mail() connection warnings (e.g. localhost:25 on Windows without an active MTA)
        set_error_handler(static function () {
            return true;
        });
        try {
            $sent = (bool)@mail($to, $encodedSubject, $body, implode($eol, $headers), $extraParams);
        } catch (\Throwable $e) {
            $sent = false;
        } finally {
            restore_error_handler();
        }

        return $sent;
    }

    /**
     * Log email to runtime/logs/email.log
     */
    private function logDispatch(string $to, string $subject, string $body, array $result): void
    {
        try {
            $dir = dirname($this->logPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $transportInfo = !empty($result['simulated'])
                ? 'SIMULATED'
                : (!empty($result['message']) ? $result['message'] : 'OK');
            $status = $result['success']
                ? "SUCCESS [{$transportInfo}]"
                : 'FAILED (' . ($result['error'] ?? 'Unknown error') . ')';
            $cleanPreview = preg_replace('/\s+/', ' ', trim(strip_tags($body)));
            $logEntry = sprintf(
                "[%s] %s | TO: %s | SUBJECT: %s\nPREVIEW: %s\n--------------------------------------------------\n",
                date('Y-m-d H:i:s'),
                $status,
                $to,
                $subject,
                substr($cleanPreview, 0, 300)
            );
            @file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Silently ignore logging failures
        }
    }
}
