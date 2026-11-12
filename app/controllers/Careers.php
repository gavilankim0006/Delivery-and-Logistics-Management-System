<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Careers extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->model('Job_application_model');
        $this->call->model('User_model');
        $this->call->model('Rider_model');
        $this->JobApplicationDocumentService = $this->call->library('JobApplicationDocumentService');
    }

    public function apply(): void
    {
        if (!auth_check()) {
            redirect('applicant/login');
            return;
        }

        $currentUserId = (int)auth_id();
        $currentUser = $this->User_model->find_by_id($currentUserId);

        // 1. If user in DB is already staff/rider, refresh session and redirect to their work dashboard
        if ($currentUser && in_array($currentUser['role_name'], ['admin', 'dispatcher', 'rider'], true)) {
            auth_login($currentUser);
            switch ($currentUser['role_name']) {
                case 'rider':
                    redirect('rider');
                    return;
                case 'dispatcher':
                    redirect('dispatch');
                    return;
                case 'admin':
                    redirect('admin');
                    return;
            }
        }

        // 2. Check if this applicant has an approved application
        $userEmail = strtolower(trim((string)(auth_user()['email'] ?? '')));
        $approvedApp = $this->db->raw(
            "SELECT * FROM job_applications WHERE (user_id = ? OR email = ?) AND status = 'approved' ORDER BY id DESC LIMIT 1",
            [$currentUserId, $userEmail]
        )->fetch(PDO::FETCH_ASSOC);

        if ($approvedApp) {
            $roleApplied = strtolower(trim((string)($approvedApp['role_applied'] ?? 'rider')));
            $targetRoleId = ($roleApplied === 'dispatcher') ? 2 : 4;
            $targetRole = ($roleApplied === 'dispatcher') ? 'dispatcher' : 'rider';

            // Ensure rider record exists in riders table if role is rider
            if ($roleApplied === 'rider') {
                $riderRow = $this->db->raw('SELECT id FROM riders WHERE user_id = ? AND deleted_at IS NULL LIMIT 1', [$currentUserId])->fetch(PDO::FETCH_ASSOC);
                if (!$riderRow) {
                    $this->Rider_model->create([
                        'user_id'      => $currentUserId,
                        'name'         => $approvedApp['full_name'],
                        'phone'        => $approvedApp['phone'],
                        'vehicle_type' => 'Motorcycle',
                        'plate_number' => 'PENDING-' . $currentUserId,
                        'status'       => 'available'
                    ]);
                }
            }

            // Upgrade role in users table
            $this->User_model->update_user($currentUserId, [
                'role_id' => $targetRoleId,
                'status'  => 'active'
            ]);

            if (empty($approvedApp['user_id'])) {
                $this->db->raw('UPDATE job_applications SET user_id = ? WHERE id = ?', [$currentUserId, (int)$approvedApp['id']]);
            }

            $refreshed = $this->User_model->find_by_id($currentUserId);
            if ($refreshed) {
                auth_login($refreshed);
            }

            flash('success', 'Your ' . ucfirst($targetRole) . ' application has been approved! Welcome to the team.');
            redirect($targetRole === 'dispatcher' ? 'dispatch' : 'rider');
            return;
        }

        // Check for pending application
        $pendingApp = $this->db->raw(
            "SELECT id, role_applied, full_name, email, created_at, status FROM job_applications WHERE (user_id = ? OR email = ?) AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$currentUserId, $userEmail]
        )->fetch(PDO::FETCH_ASSOC);

        $formData = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'role_applied' => '',
            'location' => '',
            'experience' => '',
            'availability' => '',
            'id_type' => '',
            'id_number' => '',
        ];
        $errors = [];

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!verify_csrf()) {
                if (is_json_request()) {
                    json_response(['success' => false, 'error' => 'Invalid security token. Please try again.'], 403);
                }
                flash('error', 'Invalid security token. Please try again.');
                redirect('careers/apply');
                return;
            }

            $formData = [
                'full_name' => trim((string)($_POST['full_name'] ?? '')),
                'email' => trim((string)($_POST['email'] ?? '')),
                'phone' => trim((string)($_POST['phone'] ?? '')),
                'role_applied' => trim((string)($_POST['role_applied'] ?? '')),
                'location' => trim((string)($_POST['location'] ?? '')),
                'experience' => trim((string)($_POST['experience'] ?? '')),
                'availability' => trim((string)($_POST['availability'] ?? '')),
                'id_type' => trim((string)($_POST['id_type'] ?? '')),
                'id_number' => trim((string)($_POST['id_number'] ?? '')),
            ];

            // 1. Full legal name
            if ($formData['full_name'] === '') {
                $errors['full_name'] = 'Full legal name is required.';
            } elseif (mb_strlen($formData['full_name']) < 2 || mb_strlen($formData['full_name']) > 150) {
                $errors['full_name'] = 'Full legal name must be between 2 and 150 characters.';
            }

            // 2. Role applied
            if ($formData['role_applied'] === '') {
                $errors['role_applied'] = 'Role is required.';
            } elseif (!in_array($formData['role_applied'], ['rider', 'dispatcher'], true)) {
                $errors['role_applied'] = 'Please select a valid role (Rider or Dispatcher).';
            }

            // 3. Email address
            if ($formData['email'] === '') {
                $errors['email'] = 'Email address is required.';
            } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($formData['email']) > 254) {
                $errors['email'] = 'Please enter a valid email address.';
            }

            // 4. Philippine Phone (09XXXXXXXXX or +639XXXXXXXXX)
            $cleanedPhone = preg_replace('/[\s\-\(\)\.]/', '', $formData['phone']);
            if ($formData['phone'] === '') {
                $errors['phone'] = 'Phone number is required.';
            } elseif (!preg_match('/^(09|\+639|639)\d{9}$/', $cleanedPhone)) {
                $errors['phone'] = 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';
            } else {
                if (preg_match('/^(?:09|\+639|639)(\d{9})$/', $cleanedPhone, $matches)) {
                    $formData['phone'] = '09' . $matches[1];
                }
            }

            // 5. Location
            if ($formData['location'] === '') {
                $errors['location'] = 'Current city or area is required.';
            } elseif (mb_strlen($formData['location']) > 180) {
                $errors['location'] = 'Location cannot exceed 180 characters.';
            }

            // 6. Availability
            if ($formData['availability'] === '') {
                $errors['availability'] = 'Availability is required.';
            } elseif (!in_array($formData['availability'], ['Full-time', 'Part-time', 'Weekdays', 'Weekends', 'Flexible'], true)) {
                $errors['availability'] = 'Select one of the listed availability options.';
            }

            // 7. Experience
            if ($formData['experience'] === '') {
                $errors['experience'] = 'Relevant experience is required.';
            } elseif (mb_strlen($formData['experience']) > 3000) {
                $errors['experience'] = 'Relevant experience cannot exceed 3000 characters.';
            }

            // 8. ID type
            if ($formData['id_type'] === '') {
                $errors['id_type'] = 'ID type is required.';
            } elseif (!in_array($formData['id_type'], ['Driver’s License', 'PhilID / National ID', 'UMID', 'Passport', 'Postal ID', 'Voter’s ID', 'Other government-issued ID'], true)) {
                $errors['id_type'] = 'Choose an ID type from the list.';
            }

            // 9. ID number
            if ($formData['id_number'] === '') {
                $errors['id_number'] = 'ID number is required.';
            } elseif (mb_strlen($formData['id_number']) > 100) {
                $errors['id_number'] = 'ID number cannot exceed 100 characters.';
            }

            // 10. ID document
            if (empty($_FILES['id_document']['name']) || empty($_FILES['id_document']['tmp_name'])) {
                $errors['id_document'] = 'ID document image or PDF is required.';
            }

            if (!empty($errors)) {
                if (is_json_request()) {
                    json_response(['success' => false, 'errors' => $errors], 422);
                }
                $this->call->view('careers/apply', [
                    'page_title' => 'Apply to join Dispatch',
                    'form_data' => $formData,
                    'errors' => $errors,
                ]);
                return;
            }

            try {
                $uploaded = $this->JobApplicationDocumentService->store($_FILES['id_document'] ?? []);
            } catch (Exception $e) {
                $errors['id_document'] = $e->getMessage() ?: 'Document upload rejected. Provide a valid JPEG, PNG, WebP, or PDF under 8 MB.';
                if (is_json_request()) {
                    json_response(['success' => false, 'errors' => $errors], 422);
                }
                $this->call->view('careers/apply', [
                    'page_title' => 'Apply to join Dispatch',
                    'form_data' => $formData,
                    'errors' => $errors,
                ]);
                return;
            }

            $currentUserId = auth_id();
            $created = $this->Job_application_model->create([
                'user_id' => $currentUserId ?: null,
                'full_name' => $formData['full_name'],
                'email' => $formData['email'],
                'account_email' => strtolower(trim((string)(auth_user()['email'] ?? ''))),
                'phone' => $formData['phone'],
                'role_applied' => $formData['role_applied'],
                'location' => $formData['location'],
                'experience' => $formData['experience'],
                'availability' => $formData['availability'],
                'id_type' => $formData['id_type'],
                'id_number' => $formData['id_number'],
                'id_storage_key' => $uploaded['storage_key'],
                'id_original_name' => $uploaded['original_name'],
                'id_mime_type' => $uploaded['mime_type'],
                'id_file_data' => $uploaded['file_data'] ?? null,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$created) {
                if (!empty($uploaded['storage_key'])) {
                    $path = $this->JobApplicationDocumentService->readPath($uploaded['storage_key']);
                    if ($path && is_file($path)) {
                        @unlink($path);
                    }
                }
                flash('error', 'We could not submit your application. Please check your details and try again.');
                $this->call->view('careers/apply', [
                    'page_title' => 'Apply to join Dispatch',
                    'form_data' => $formData,
                    'errors' => ['form' => 'Unable to save application to database. Please try again.'],
                ]);
                return;
            }

            $msg = 'Application received. Our recruitment team will review your details.';
            if (is_json_request()) {
                json_response(['success' => true, 'message' => $msg]);
            }
            flash('success', $msg);
            redirect('careers/apply');
            return;
        }

        $this->call->view('careers/apply', [
            'page_title' => 'Apply to join Dispatch',
            'form_data' => $formData,
            'errors' => $errors,
            'pending_application' => $pendingApp ?: null,
        ]);
    }
}
