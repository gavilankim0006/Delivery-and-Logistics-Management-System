<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Addresses extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->model('Address_model');
        require_role('customer');
    }

    protected function currentCustomerId(): ?int
    {
        $user = auth_user();
        return isset($user['customer_id']) && (int)$user['customer_id'] > 0
            ? (int)$user['customer_id']
            : null;
    }

    protected function render_form(string $view, array $formData, array $errors = [], ?string $formError = null, ?int $addressId = null): void
    {
        $this->call->view('addresses/' . $view, [
            'page_title' => $view === 'new' ? 'Add saved address' : 'Edit saved address',
            'form_data' => $formData,
            'errors' => $errors,
            'form_error' => $formError,
            'address_id' => $addressId
        ]);
    }

    protected function validateAddress(array $input): array
    {
        $data = [
            'label' => trim($input['label'] ?? ''),
            'address_line' => trim($input['address_line'] ?? ''),
            'contact_name' => trim($input['contact_name'] ?? ''),
            'contact_phone' => trim($input['contact_phone'] ?? ''),
            'notes' => trim($input['notes'] ?? '')
        ];
        $errors = [];
        if ($data['label'] !== '' && mb_strlen($data['label']) > 100) {
            $errors['label'] = 'Please keep the label to 100 characters or fewer.';
        }
        foreach ([
            'address_line' => [$data['address_line'], 500],
            'contact_name' => [$data['contact_name'], 100],
            'contact_phone' => [$data['contact_phone'], 30]
        ] as $field => [$value, $maxLength]) {
            if ($value === '') {
                $errors[$field] = 'This field is required.';
            } elseif (mb_strlen($value) > $maxLength) {
                $errors[$field] = "Please keep this to {$maxLength} characters or fewer.";
            }
        }

        // Philippine phone validation with normalization to 09XXXXXXXXX
        $cleanedPhone = preg_replace('/[\s\-\(\)\.]/', '', (string)$data['contact_phone']);
        if ($cleanedPhone === '' || !preg_match('/^(?:09|\+639|639)\d{9}$/', $cleanedPhone)) {
            $errors['contact_phone'] = 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';
        } else {
            if (preg_match('/^(?:09|\+639|639)(\d{9})$/', $cleanedPhone, $matches)) {
                $data['contact_phone'] = '09' . $matches[1];
            }
        }

        if (mb_strlen($data['notes']) > 300) {
            $errors['notes'] = 'Please keep notes to 300 characters or fewer.';
        }

        return [$data, $errors];
    }

    public function new()
    {
        $this->render_form('new', []);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('addresses');
            return;
        }
        if (!verify_csrf()) {
            $this->render_form('new', $_POST, [], 'Your session expired. Reload the form and try again.');
            return;
        }
        $customerId = $this->currentCustomerId();
        if (!$customerId) {
            http_response_code(403);
            return;
        }
        [$data, $errors] = $this->validateAddress($_POST);
        if ($errors) {
            $this->render_form('new', $_POST, $errors);
            return;
        }
        try {
            $id = $this->Address_model->create(array_merge($data, ['customer_id' => $customerId]));
            if (!$id) {
                throw new \RuntimeException('Failed to save address record.');
            }
            flash('success', 'Saved address added.');
            redirect('addresses');
        } catch (\Throwable $e) {
            error_log('Saved address creation failed: ' . $e->getMessage());
            $this->render_form('new', $_POST, [], 'We could not save this address. Please check the details and try again.');
        }
    }

    public function edit(int $id)
    {
        $customerId = $this->currentCustomerId();
        $address = $customerId ? $this->Address_model->get_owned($id, $customerId) : null;
        if (!$address) {
            http_response_code(404);
            echo 'Address not found.';
            return;
        }
        $this->render_form('edit', $address, [], null, $id);
    }

    public function update(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('addresses');
            return;
        }
        if (!verify_csrf()) {
            $customerId = $this->currentCustomerId();
            if (!$customerId || !$this->Address_model->get_owned($id, $customerId)) {
                http_response_code(404);
                echo 'Address not found.';
                return;
            }
            $this->render_form('edit', $_POST, [], 'Your session expired. Reload the form and try again.', $id);
            return;
        }
        $customerId = $this->currentCustomerId();
        if (!$customerId) {
            http_response_code(403);
            return;
        }
        if (!$this->Address_model->get_owned($id, $customerId)) {
            http_response_code(404);
            echo 'Address not found.';
            return;
        }
        [$data, $errors] = $this->validateAddress($_POST);
        if ($errors) {
            $this->render_form('edit', $_POST, $errors, null, $id);
            return;
        }
        try {
            if (!$this->Address_model->update_owned($id, $customerId, $data)) {
                http_response_code(404);
                return;
            }
            flash('success', 'Saved address updated.');
            redirect('addresses');
        } catch (\Throwable $e) {
            error_log('Saved address update failed: ' . $e->getMessage());
            $this->render_form('edit', $_POST, [], 'We could not save these changes. Please check the details and try again.', $id);
        }
    }

    public function delete(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('addresses');
            return;
        }
        if (!verify_csrf()) {
            flash('error', 'Your session expired. Reload the page and try again.');
            redirect('addresses');
            return;
        }
        $customerId = $this->currentCustomerId();
        if (!$customerId) {
            http_response_code(403);
            return;
        }
        try {
            $result = $this->Address_model->delete_owned($id, $customerId);
            if ($result === 'referenced') {
                flash('error', 'This address is used by an order and cannot be deleted.');
            } elseif ($result) {
                flash('success', 'Saved address deleted.');
            } else {
                flash('error', 'Address not found or permission denied.');
            }
        } catch (\Throwable $e) {
            error_log('Saved address deletion failed: ' . $e->getMessage());
            flash('error', 'We could not delete this address.');
        }
        redirect('addresses');
    }

    public function index()
    {
        $customerId = $this->currentCustomerId();
        if ($customerId === null) {
            http_response_code(403);
            return;
        }

        $this->call->view('addresses/index', [
            'page_title' => 'Saved addresses',
            'addresses' => $this->Address_model->get_by_customer($customerId)
        ]);
    }
}
