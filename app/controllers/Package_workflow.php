<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Package_workflow extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->model('Package_workflow_model');
        require_role('customer');
    }

    private function customerId(): int
    {
        return (int)(auth_user()['customer_id'] ?? 0);
    }

    private function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'))
            || isset($_GET['ajax']) || isset($_GET['json']);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    public function index(): void
    {
        $id = $this->customerId();
        if (!$id) {
            http_response_code(403);
            return;
        }

        $conversations = $this->Package_workflow_model->conversations($id);
        $eligibleOrders = $this->Package_workflow_model->eligible_orders($id);

        $token = trim((string)($_GET['token'] ?? ''));
        $tokenHandoff = null;
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $tokenHandoff = $this->Package_workflow_model->find_by_token($token, $id);
        }

        $activeHandoffId = (int)($_GET['c'] ?? 0);
        $activeHandoff = null;
        $activeMessages = [];

        if ($activeHandoffId > 0) {
            $activeHandoff = $this->Package_workflow_model->conversation($activeHandoffId, $id);
            if ($activeHandoff) {
                $this->Package_workflow_model->mark_read($activeHandoffId, $id);
                $activeMessages = $this->Package_workflow_model->messages($activeHandoffId, $id);
            }
        } elseif (!empty($conversations) && !isset($_GET['new'])) {
            // Default to the first conversation if none selected
            $first = $conversations[0];
            $activeHandoffId = (int)$first['id'];
            $activeHandoff = $this->Package_workflow_model->conversation($activeHandoffId, $id);
            if ($activeHandoff) {
                $this->Package_workflow_model->mark_read($activeHandoffId, $id);
                $activeMessages = $this->Package_workflow_model->messages($activeHandoffId, $id);
            }
        }

        if ($this->isAjax() && isset($_GET['format']) && $_GET['format'] === 'json') {
            $this->json([
                'success' => true,
                'conversations' => $conversations,
                'active_handoff' => $activeHandoff,
                'messages' => $activeMessages,
                'customer_id' => $id,
            ]);
        }

        $this->call->view('package_workflow/index', [
            'page_title' => 'Package Messenger',
            'customer_id' => $id,
            'conversations' => $conversations,
            'active_handoff' => $activeHandoff,
            'active_messages' => $activeMessages,
            'eligible_orders' => $eligibleOrders,
            'token' => $token,
            'token_handoff' => $tokenHandoff,
            'preset_order_id' => (int)($_GET['order_id'] ?? 0),
        ]);
    }

    public function conversation(int $handoffId): void
    {
        $id = $this->customerId();
        if (!$id) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
            http_response_code(403);
            return;
        }

        $handoff = $this->Package_workflow_model->conversation($handoffId, $id);
        if (!$handoff) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
            http_response_code(404);
            return;
        }

        $this->Package_workflow_model->mark_read($handoffId, $id);
        $messages = $this->Package_workflow_model->messages($handoffId, $id);

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'handoff' => $handoff,
                'messages' => $messages,
                'customer_id' => $id,
            ]);
        }

        // Web view: render unified messenger with this conversation selected
        $_GET['c'] = $handoffId;
        $this->index();
    }

    public function messages(int $handoffId): void
    {
        $id = $this->customerId();
        if (!$id) {
            $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $handoff = $this->Package_workflow_model->conversation($handoffId, $id);
        if (!$handoff) {
            $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }

        $this->Package_workflow_model->mark_read($handoffId, $id);
        $messages = $this->Package_workflow_model->messages($handoffId, $id);

        $this->json([
            'success' => true,
            'handoff' => $handoff,
            'messages' => $messages,
            'customer_id' => $id,
        ]);
    }

    public function send_message(int $handoffId): void
    {
        $id = $this->customerId();
        if (!$id) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
            http_response_code(403);
            return;
        }

        // Support standard POST or raw JSON body
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        $body = trim((string)($_POST['body'] ?? ($jsonData['body'] ?? '')));
        $csrfToken = $_POST['csrf_token'] ?? ($jsonData['csrf_token'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($csrfToken)) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Invalid CSRF token or request method'], 400);
            flash('error', 'Session expired or invalid security token. Please try again.');
            redirect('package-workflow/conversation/' . $handoffId);
            return;
        }

        $result = $this->Package_workflow_model->send_message($handoffId, $id, $body);

        if (!$result) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Unable to send message. Handoff may be closed or message invalid.'], 422);
            flash('error', 'Unable to send message. Handoff may be closed or message invalid.');
            redirect('package-workflow/conversation/' . $handoffId);
            return;
        }

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'message' => $result,
            ]);
        }

        redirect('package-workflow/conversation/' . $handoffId);
    }

    public function confirm_handoff(int $handoffId): void
    {
        $id = $this->customerId();
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $csrfToken = $_POST['csrf_token'] ?? ($jsonData['csrf_token'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($csrfToken) || !$this->Package_workflow_model->confirm_handoff($handoffId, $id)) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Unable to confirm receipt. Check handoff status.'], 400);
            flash('error', 'Unable to confirm receipt.');
        } else {
            if ($this->isAjax()) $this->json(['success' => true, 'message' => 'Package receipt confirmed.']);
            flash('success', 'Package receipt confirmed.');
        }

        if ($this->isAjax()) return;
        redirect('package-workflow/conversation/' . $handoffId);
    }

    public function handoff(): void
    {
        $id = $this->customerId();
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        $order = (int)($_POST['order_id'] ?? ($jsonData['order_id'] ?? 0));
        $recipient = (int)($_POST['recipient_customer_id'] ?? ($jsonData['recipient_customer_id'] ?? 0));
        $csrfToken = $_POST['csrf_token'] ?? ($jsonData['csrf_token'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($csrfToken) || !$id) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Invalid request.'], 400);
            flash('error', 'Invalid request or session expired.');
            redirect('package-workflow');
            return;
        }

        $res = $this->Package_workflow_model->create_handoff($order, $id, $recipient);
        if (!$res || empty($res['token'])) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Unable to create handoff. Confirm order eligibility and recipient.'], 422);
            flash('error', 'Unable to create handoff. Confirm order eligibility and recipient.');
            redirect('package-workflow');
            return;
        }

        $token = $res['token'];
        $handoffId = $res['handoff_id'];
        $link = base_url('package-workflow?token=' . rawurlencode($token));

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'handoff_id' => $handoffId,
                'token' => $token,
                'link' => $link,
                'message' => 'Package handoff initiated successfully.',
            ]);
        }

        flash('success', 'Private recipient-bound confirmation link (send only to intended customer): ' . $link);
        redirect('package-workflow/conversation/' . $handoffId);
    }

    public function confirm(): void
    {
        $id = $this->customerId();
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        $token = (string)($_POST['token'] ?? ($jsonData['token'] ?? ''));
        $csrfToken = $_POST['csrf_token'] ?? ($jsonData['csrf_token'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($csrfToken) || !$id || !preg_match('/^[a-f0-9]{64}$/', $token) || !$this->Package_workflow_model->confirm($token, $id)) {
            if ($this->isAjax()) $this->json(['success' => false, 'error' => 'Handoff link is invalid, expired, already used, or intended for another customer.'], 400);
            flash('error', 'Handoff link is invalid, expired, already used, or intended for another customer.');
        } else {
            if ($this->isAjax()) $this->json(['success' => true, 'message' => 'Package receipt confirmed and recorded.']);
            flash('success', 'Package receipt confirmed and recorded.');
        }

        if ($this->isAjax()) return;
        redirect('package-workflow');
    }

    public function api_customers(): void
    {
        $id = $this->customerId();
        $query = substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $customers = $query === '' ? [] : $this->Package_workflow_model->search_customers($id, $query);
        $this->json(['success' => true, 'customers' => $customers]);
    }

    public function api_conversations(): void
    {
        $id = $this->customerId();
        $conversations = $this->Package_workflow_model->conversations($id);
        $this->json(['success' => true, 'conversations' => $conversations]);
    }

    public function report(): void
    {
        $id = $this->customerId();
        $order = (int)($_POST['order_id'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf() || !$id || strlen($description) < 10 || strlen($description) > 4000 || !$this->Package_workflow_model->create_report($order, $id, $description)) {
            flash('error', 'Unable to submit report. Check order eligibility and description (10–4000 characters).');
        } else {
            flash('success', 'Missing-package report submitted.');
        }

        redirect('orders/' . $order);
    }
}
