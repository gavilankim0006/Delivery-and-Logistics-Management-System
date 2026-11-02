<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Careers extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->model('Job_application_model');
        $this->call->library('JobApplicationDocumentService');
    }

    public function apply(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token. Please try again.');
                redirect('careers/apply');
                return;
            }

            $data = [
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
            if (mb_strlen($data['full_name']) < 2 || mb_strlen($data['full_name']) > 150
                || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 254
                || mb_strlen($data['phone']) < 7 || mb_strlen($data['phone']) > 40
                || !in_array($data['role_applied'], ['rider', 'dispatcher'], true)
                || $data['location'] === '' || mb_strlen($data['location']) > 180
                || $data['experience'] === '' || mb_strlen($data['experience']) > 3000
                || $data['availability'] === '' || mb_strlen($data['availability']) > 180
                || $data['id_type'] === '' || mb_strlen($data['id_type']) > 100
                || $data['id_number'] === '' || mb_strlen($data['id_number']) > 100) {
                flash('error', 'Check all fields. Please provide valid contact, role, experience, availability, and ID details.');
                redirect('careers/apply');
                return;
            }

            $document = null;
            try {
                $document = $this->JobApplicationDocumentService->store($_FILES['id_document'] ?? []);
                $data['id_storage_key'] = $document['storage_key'];
                $data['id_mime_type'] = $document['mime_type'];
                $data['id_original_name'] = $document['original_name'];
                $data['id_file_data'] = $document['file_data'] ?? null;
                $this->Job_application_model->create($data);
                flash('success', 'Application received. Our team will review it and contact you using the details provided.');
            } catch (Throwable $e) {
                if ($document) {
                    $this->JobApplicationDocumentService->delete($document['storage_key']);
                }
                flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Application could not be submitted. Please try again later.');
            }
            redirect('careers/apply');
            return;
        }

        $this->call->view('careers/apply', ['page_title' => 'Apply to join Dispatch']);
    }
}
