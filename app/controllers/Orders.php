<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Orders Controller
 * Handles customer order creation, detail tracking, order listing, and cancellation.
 */
class Orders extends Controller
{
    protected function currentCustomerId(): ?int
    {
        $user = auth_user();
        return isset($user['customer_id']) && (int)$user['customer_id'] > 0
            ? (int)$user['customer_id']
            : null;
    }

    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->helper('fee');
        $this->call->model('Order_model');
        require_role(['customer', 'dispatcher', 'admin']);
        $this->call->library('DeliveryControlService');
    }

    /**
     * List orders for the current authenticated user (customer gets their own, staff gets all)
     */
    public function index()
    {
        $role = auth_role();
        $status = trim($_GET['status'] ?? '');
        $validStatuses = ['draft', 'pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed'];
        if (!in_array($status, $validStatuses, true)) {
            $status = '';
        }

        $date = trim($_GET['date'] ?? '');
        if ($date !== '') {
            $dateParts = explode('-', $date);
            if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
                $date = '';
            }
        }

        $filters = [
            'status' => $status,
            'date' => $date,
            'search' => substr(trim($_GET['search'] ?? ''), 0, 100),
            'limit' => 20,
            'offset' => (max(1, (int)($_GET['page'] ?? 1)) - 1) * 20
        ];
        if ($role === 'customer') {
            $customerId = $this->currentCustomerId();
            if ($customerId === null) {
                http_response_code(403);
                return;
            }
            $filters['customer_id'] = $customerId;
        }

        $orders = $this->Order_model->get_list($filters);
        $totalOrders = $this->Order_model->count_list($filters);
        $page = (int)floor($filters['offset'] / $filters['limit']) + 1;

        $this->call->view('orders/index', [
            'page_title' => $role === 'customer' ? 'My Orders' : 'Orders',
            'orders' => $orders,
            'filters' => $filters,
            'role' => $role,
            'page' => $page,
            'page_size' => $filters['limit'],
            'total_orders' => $totalOrders,
            'total_pages' => max(1, (int)ceil($totalOrders / $filters['limit']))
        ]);
    }

    /**
     * Show new delivery order form with map route preview and fee estimator
     */
    public function new_order()
    {
        if (auth_role() !== 'customer') {
            redirect('orders');
            return;
        }

        $this->render_new_order();
    }

    protected function render_new_order(array $formData = [], array $errors = [], ?string $formError = null): void
    {
        $this->call->view('orders/new', [
            'page_title' => 'New delivery',
            'user' => auth_user(),
            'rates' => get_delivery_rates(),
            'maps_js_key' => getenv('GOOGLE_MAPS_MAPS_JS_API_KEY') ?: '',
            'route_api_endpoint' => base_url('api/route/calculate'),
            'form_data' => $formData,
            'errors' => $errors,
            'form_error' => $formError
        ]);
    }

    /**
     * Process new order creation transaction
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('orders/new');
            return;
        }

        if (!verify_csrf()) {
            $this->render_new_order([], [], 'Your session expired. Reload the form and try again.');
            return;
        }

        $customerId = $this->currentCustomerId();
        if (!$customerId) {
            http_response_code(403);
            return;
        }

        $pickupAddress = trim($_POST['pickup_address'] ?? '');
        $pickupContactName = trim($_POST['pickup_contact_name'] ?? '');
        $pickupContactPhone = trim($_POST['pickup_contact_phone'] ?? '');
        $pickupNotes = trim($_POST['pickup_notes'] ?? '');

        $dropoffAddress = trim($_POST['dropoff_address'] ?? '');
        $dropoffContactName = trim($_POST['dropoff_contact_name'] ?? '');
        $dropoffContactPhone = trim($_POST['dropoff_contact_phone'] ?? '');
        $dropoffNotes = trim($_POST['dropoff_notes'] ?? '');

        $formData = $_POST;
        $parcelDesc = trim($_POST['parcel_description'] ?? '');
        $weightInput = trim($_POST['parcel_weight'] ?? '');
        $parcelWeight = is_numeric($weightInput) ? (float)$weightInput : 0.0;
        $parcelSize = strtoupper(trim($formData['parcel_size'] ?? ''));
        $isFragile = !empty($formData['is_fragile']) ? 1 : 0;
        $distanceInput = trim($formData['distance_km'] ?? '');
        $distanceKm = is_numeric($distanceInput) ? (float)$distanceInput : 0.0;
        $pickupLatInput = trim($formData['pickup_lat'] ?? '');
        $pickupLngInput = trim($formData['pickup_lng'] ?? '');
        $dropoffLatInput = trim($formData['dropoff_lat'] ?? '');
        $dropoffLngInput = trim($formData['dropoff_lng'] ?? '');
        $pickupLat = $pickupLatInput !== '' && is_numeric($pickupLatInput) ? (float)$pickupLatInput : null;
        $pickupLng = $pickupLngInput !== '' && is_numeric($pickupLngInput) ? (float)$pickupLngInput : null;
        $dropoffLat = $dropoffLatInput !== '' && is_numeric($dropoffLatInput) ? (float)$dropoffLatInput : null;
        $dropoffLng = $dropoffLngInput !== '' && is_numeric($dropoffLngInput) ? (float)$dropoffLngInput : null;
        $hasMapLocations = $pickupLat !== null && $pickupLng !== null && $dropoffLat !== null && $dropoffLng !== null;
        $errors = [];
        if (mb_strlen($pickupNotes) > 1000) {
            $errors['pickup_notes'] = 'Please keep pickup notes to 1000 characters or fewer.';
        }
        if (mb_strlen($dropoffNotes) > 1000) {
            $errors['dropoff_notes'] = 'Please keep drop-off notes to 1000 characters or fewer.';
        }

        foreach ([
            'pickup_address' => [$pickupAddress, 500],
            'pickup_contact_name' => [$pickupContactName, 100],
            'pickup_contact_phone' => [$pickupContactPhone, 30],
            'dropoff_address' => [$dropoffAddress, 500],
            'dropoff_contact_name' => [$dropoffContactName, 100],
            'dropoff_contact_phone' => [$dropoffContactPhone, 30],
            'parcel_description' => [$parcelDesc, 255]
        ] as $field => [$value, $maxLength]) {
            if ($value === '') {
                $errors[$field] = 'This field is required.';
            } elseif (mb_strlen($value) > $maxLength) {
                $errors[$field] = "Please keep this to {$maxLength} characters or fewer.";
            }
        }
        foreach (['pickup_contact_phone' => $pickupContactPhone, 'dropoff_contact_phone' => $dropoffContactPhone] as $field => $phone) {
            if ($phone !== '' && !preg_match('/^\\+?[0-9][0-9 ()-]{6,28}$/', $phone)) {
                $errors[$field] = 'Enter a valid phone number.';
            }
        }
        if (!is_numeric($weightInput) || $parcelWeight <= 0 || $parcelWeight > 1000) {
            $errors['parcel_weight'] = 'Enter a weight greater than 0 and no more than 1000 kg.';
        }
        if (!in_array($parcelSize, ['S', 'M', 'L'], true)) {
            $errors['parcel_size'] = 'Choose a parcel size.';
        }
        if ($pickupAddress !== '' && strcasecmp($pickupAddress, $dropoffAddress) === 0) {
            $errors['dropoff_address'] = 'Pickup and drop-off addresses cannot be identical.';
        }
        foreach ([
            'pickup' => [$pickupLatInput, $pickupLngInput, $pickupLat, $pickupLng],
            'dropoff' => [$dropoffLatInput, $dropoffLngInput, $dropoffLat, $dropoffLng]
        ] as $stop => [$latInput, $lngInput, $lat, $lng]) {
            if (($latInput !== '' || $lngInput !== '') && ($lat === null || $lng === null || $lat < 4.0 || $lat > 22.0 || $lng < 115.0 || $lng > 128.0)) {
                $errors[$stop . '_address'] = 'Choose a valid map location in the Philippines.';
            }
        }
        if ($hasMapLocations && !$errors) {
            try {
                $maps = $this->call->library('GoogleMapsService');
                $route = $maps->route(
                    ['lat' => $pickupLat, 'lng' => $pickupLng],
                    ['lat' => $dropoffLat, 'lng' => $dropoffLng]
                );
                $distanceKm = (float)$route['distance_km'];
                $distanceInput = (string)$distanceKm;
                $formData['distance_km'] = $distanceInput;
            } catch (Throwable $e) {
                $errors['dropoff_address'] = $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : 'Unable to confirm the Google Maps route. Please check the selected locations.';
            }
        }
        if (!is_numeric($distanceInput) || $distanceKm <= 0 || $distanceKm > 10000) {
            $errors['distance_km'] = 'Enter a distance greater than 0 and no more than 10000 km.';
        }

        if (!empty($errors)) {
            $this->render_new_order($formData, $errors);
            return;
        }

        try {
            $feeCalc = calculate_delivery_fee($distanceKm, $parcelWeight);

            $orderData = [
                'customer_id' => $customerId,
                'distance_km' => $distanceKm,
                'estimated_duration_min' => (int)ceil($distanceKm * 2.8),
                'delivery_fee' => $feeCalc['total_fee'],
                'actor_type' => 'customer',
                'actor_id' => auth_id(),
                'pickup' => [
                    'label' => 'Pickup Location',
                    'address_line' => $pickupAddress,
                    'latitude' => $pickupLat,
                    'longitude' => $pickupLng,
                    'contact_name' => $pickupContactName,
                    'contact_phone' => $pickupContactPhone,
                    'notes' => $pickupNotes
                ],
                'dropoff' => [
                    'label' => 'Drop-off Location',
                    'address_line' => $dropoffAddress,
                    'latitude' => $dropoffLat,
                    'longitude' => $dropoffLng,
                    'contact_name' => $dropoffContactName,
                    'contact_phone' => $dropoffContactPhone,
                    'notes' => $dropoffNotes
                ],
                'parcel' => [
                    'description' => $parcelDesc,
                    'weight_kg' => $parcelWeight,
                    'size' => $parcelSize,
                    'is_fragile' => $isFragile
                ]
            ];

            // Execute creation transaction
            $created = $this->Order_model->create_order($orderData);

            flash('success', "Draft order #{$created['order_no']} saved successfully!");
            redirect('orders/' . $created['order_id']);
            return;
        } catch (\InvalidArgumentException $e) {
            $this->render_new_order($formData, [], 'Please enter two valid locations in the Philippines.');
            return;
        } catch (\Throwable $e) {
            error_log('Order creation failed: ' . $e->getMessage());
            $this->render_new_order($formData, [], 'We could not save this delivery. Please check the details and try again.');
            return;
        }
    }

    /**
     * Show the edit form for a customer's own draft order.
     */
    public function edit($id)
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            http_response_code(404);
            $this->call->view('orders/not_found', ['page_title' => 'Order not found']);
            return;
        }
        $id = (int)$id;

        if (auth_role() !== 'customer') {
            redirect('orders');
            return;
        }

        $customerId = $this->currentCustomerId();
        $order = $customerId ? $this->Order_model->get_by_id($id, $customerId) : null;
        if (!$order || $order['status'] !== 'draft') {
            http_response_code(404);
            $this->call->view('orders/not_found', ['page_title' => 'Order not found']);
            return;
        }

        $this->render_edit_order($id, [
            'pickup_address' => $order['pickup_address'],
            'pickup_contact_name' => $order['pickup_contact_name'],
            'pickup_contact_phone' => $order['pickup_phone'],
            'pickup_notes' => $order['pickup_notes'],
            'dropoff_address' => $order['dropoff_address'],
            'dropoff_contact_name' => $order['dropoff_contact_name'],
            'dropoff_contact_phone' => $order['dropoff_phone'],
            'dropoff_notes' => $order['dropoff_notes'],
            'parcel_description' => $order['parcel_description'],
            'parcel_weight' => $order['weight_kg'],
            'parcel_size' => $order['parcel_size'],
            'is_fragile' => $order['is_fragile'],
            'distance_km' => $order['distance_km']
        ]);
    }

    protected function render_edit_order(int $id, array $formData, array $errors = [], ?string $formError = null): void
    {
        $this->call->view('orders/edit', [
            'page_title' => 'Edit draft order',
            'form_data' => $formData,
            'errors' => $errors,
            'form_error' => $formError,
            'order_id' => $id
        ]);
    }

    /**
     * Soft-delete a customer's own draft order.
     */
    public function delete_draft($id): void
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('orders');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('orders/' . $id);
            return;
        }
        if (auth_role() !== 'customer') {
            http_response_code(403);
            return;
        }
        if (!verify_csrf()) {
            flash('error', 'Your session expired. Reload the page and try again.');
            redirect('orders/' . $id);
            return;
        }

        $customerId = $this->currentCustomerId();
        if (!$customerId) {
            http_response_code(403);
            return;
        }
        if (!$this->Order_model->soft_delete_draft($id, $customerId)) {
            flash('error', 'Only your own draft order can be deleted.');
            redirect('orders/' . $id);
            return;
        }

        flash('success', 'Draft order deleted.');
        redirect('orders');
    }

    /**
     * Update editable details on a customer's own draft order.
     */
    public function update($id)
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('orders');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('orders/' . $id);
            return;
        }
        if (auth_role() !== 'customer') {
            http_response_code(403);
            return;
        }
        if (!verify_csrf()) {
            $customerId = $this->currentCustomerId();
            $ownedOrder = $customerId ? $this->Order_model->get_by_id($id, $customerId) : null;
            if (!$ownedOrder || $ownedOrder['status'] !== 'draft') {
                http_response_code(404);
                $this->call->view('orders/not_found', ['page_title' => 'Order not found']);
                return;
            }
            $this->render_edit_order($id, $_POST, [], 'Your session expired. Reload the form and try again.');
            return;
        }

        $customerId = $this->currentCustomerId();
        if (!$customerId) {
            http_response_code(403);
            return;
        }
        $order = $this->Order_model->get_by_id($id, $customerId);
        if (!$order || $order['status'] !== 'draft') {
            http_response_code(404);
            $this->call->view('orders/not_found', ['page_title' => 'Order not found']);
            return;
        }

        $formData = array_map(static fn($value) => is_scalar($value) ? (string)$value : '', $_POST);
        $pickupAddress = trim($formData['pickup_address'] ?? '');
        $pickupContactName = trim($formData['pickup_contact_name'] ?? '');
        $pickupContactPhone = trim($formData['pickup_contact_phone'] ?? '');
        $pickupNotes = trim($formData['pickup_notes'] ?? '');
        $dropoffAddress = trim($formData['dropoff_address'] ?? '');
        $dropoffContactName = trim($formData['dropoff_contact_name'] ?? '');
        $dropoffContactPhone = trim($formData['dropoff_contact_phone'] ?? '');
        $dropoffNotes = trim($formData['dropoff_notes'] ?? '');
        $parcelDesc = trim($formData['parcel_description'] ?? '');
        $weightInput = trim($formData['parcel_weight'] ?? '');
        $parcelWeight = is_numeric($weightInput) ? (float)$weightInput : 0.0;
        $parcelSize = strtoupper(trim($formData['parcel_size'] ?? ''));
        $isFragile = !empty($formData['is_fragile']) ? 1 : 0;
        $distanceInput = trim($formData['distance_km'] ?? '');
        $distanceKm = is_numeric($distanceInput) ? (float)$distanceInput : 0.0;
        $errors = [];

        foreach ([
            'pickup_address' => [$pickupAddress, 500],
            'pickup_contact_name' => [$pickupContactName, 100],
            'pickup_contact_phone' => [$pickupContactPhone, 30],
            'dropoff_address' => [$dropoffAddress, 500],
            'dropoff_contact_name' => [$dropoffContactName, 100],
            'dropoff_contact_phone' => [$dropoffContactPhone, 30],
            'parcel_description' => [$parcelDesc, 255]
        ] as $field => [$value, $maxLength]) {
            if ($value === '') {
                $errors[$field] = 'This field is required.';
            } elseif (mb_strlen($value) > $maxLength) {
                $errors[$field] = "Please keep this to {$maxLength} characters or fewer.";
            }
        }
        foreach (['pickup_notes' => $pickupNotes, 'dropoff_notes' => $dropoffNotes] as $field => $notes) {
            if (mb_strlen($notes) > 1000) {
                $errors[$field] = 'Please keep notes to 1000 characters or fewer.';
            }
        }
        foreach (['pickup_contact_phone' => $pickupContactPhone, 'dropoff_contact_phone' => $dropoffContactPhone] as $field => $phone) {
            if ($phone !== '' && !preg_match('/^\\+?[0-9][0-9 ()-]{6,28}$/', $phone)) {
                $errors[$field] = 'Enter a valid phone number.';
            }
        }
        if (!is_numeric($weightInput) || $parcelWeight <= 0 || $parcelWeight > 1000) {
            $errors['parcel_weight'] = 'Enter a weight greater than 0 and no more than 1000 kg.';
        }
        if (!in_array($parcelSize, ['S', 'M', 'L'], true)) {
            $errors['parcel_size'] = 'Choose a parcel size.';
        }
        if (!is_numeric($distanceInput) || $distanceKm <= 0 || $distanceKm > 10000) {
            $errors['distance_km'] = 'Enter a distance greater than 0 and no more than 10000 km.';
        }
        if ($pickupAddress !== '' && strcasecmp($pickupAddress, $dropoffAddress) === 0) {
            $errors['dropoff_address'] = 'Pickup and drop-off addresses cannot be identical.';
        }
        if ($errors) {
            $this->render_edit_order($id, $formData, $errors);
            return;
        }

        try {
            $feeCalc = calculate_delivery_fee($distanceKm, $parcelWeight);
            $updated = $this->Order_model->update_draft_order($id, $customerId, [
                'distance_km' => $distanceKm,
                'estimated_duration_min' => (int)ceil($distanceKm * 2.8),
                'delivery_fee' => $feeCalc['total_fee'],
                'pickup' => [
                    'address_line' => $pickupAddress,
                    'contact_name' => $pickupContactName,
                    'contact_phone' => $pickupContactPhone,
                    'notes' => $pickupNotes
                ],
                'dropoff' => [
                    'address_line' => $dropoffAddress,
                    'contact_name' => $dropoffContactName,
                    'contact_phone' => $dropoffContactPhone,
                    'notes' => $dropoffNotes
                ],
                'parcel' => [
                    'description' => $parcelDesc,
                    'weight_kg' => $parcelWeight,
                    'size' => $parcelSize,
                    'is_fragile' => $isFragile
                ]
            ]);
            if (!$updated) {
                http_response_code(404);
                $this->call->view('orders/not_found', ['page_title' => 'Order not found']);
                return;
            }
            flash('success', 'Draft order updated successfully.');
            redirect('orders/' . $id);
        } catch (\Throwable $e) {
            error_log('Draft order update failed: ' . $e->getMessage());
            $this->render_edit_order($id, $formData, [], 'We could not save these changes. Please check the details and try again.');
        }
    }

    /**
     * Start a customer-owned PayMongo checkout from an explicit POST.
     */
    public function checkout($id)
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('orders');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || auth_role() !== 'customer' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid checkout request or security token.');
            redirect('orders/' . $id);
            return;
        }
        $customerId = $this->currentCustomerId();
        if (!$customerId) {
            http_response_code(403);
            return;
        }
        try {
            $this->call->library('PaymentCheckoutService');
            $successUrl = base_url('orders/' . $id) . '?payment=return';
            $cancelUrl = base_url('orders/' . $id) . '?payment=cancelled';
            $successHost = strtolower((string)(parse_url($successUrl, PHP_URL_HOST) ?? ''));
            $allowedLocalHost = config_item('environment') === 'development' && in_array($successHost, ['localhost', '127.0.0.1'], true);
            if (parse_url($successUrl, PHP_URL_SCHEME) !== 'https' && !$allowedLocalHost) {
                throw new RuntimeException('PayMongo checkout requires HTTPS except for local development hosts.');
            }
            $checkout = (new PaymentCheckoutService($this->db, $this->Order_model))->start($id, $customerId, $successUrl, $cancelUrl);
            header('Location: ' . $checkout['checkout_url'], true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('PayMongo checkout initialization failed for order ' . $id . ': ' . $e->getMessage());
            flash('error', 'We could not start payment. Your order has not been marked paid. Please try again or contact support.');
            redirect('orders/' . $id);
        }
    }

    public function location($id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');
        if (!is_numeric($id) || (int)$id <= 0 || strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || auth_role() !== 'customer') {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Location unavailable.']);
            return;
        }
        $id = (int)$id;

        $customerId = $this->currentCustomerId();
        $location = $customerId ? $this->Order_model->get_customer_shared_location($id, $customerId) : null;
        if (!$location) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No recent rider-shared location is available.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'latitude' => (float)$location['latitude'],
            'longitude' => (float)$location['longitude'],
            'accuracy_m' => (float)$location['accuracy_m'],
            'received_at' => date(DATE_ATOM, strtotime($location['received_at'])),
            'age_seconds' => (int)$location['age_seconds'],
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Show order tracking and detail page
     */
    public function show($id)
    {
        // Gracefully recover if user visits or is redirected to malformed URL (e.g. 11paymentcancelled)
        if (is_string($id) && preg_match('/^(\d+)payment(cancelled|return)$/', $id, $matches)) {
            redirect('orders/' . $matches[1] . '?payment=' . $matches[2]);
            return;
        }

        if (!is_numeric($id) || (int)$id <= 0) {
            http_response_code(404);
            $this->call->view('orders/not_found', ['page_title' => 'Order not found']);
            return;
        }
        $id = (int)$id;

        $role = auth_role();
        $customerId = ($role === 'customer') ? $this->currentCustomerId() : null;

        $order = $this->Order_model->get_by_id($id, $customerId);

        if (!$order) {
            http_response_code(404);
            $this->call->view('orders/not_found', ['page_title' => 'Order not found']);
            return;
        }

        $this->call->view('orders/show', [
            'page_title' => "Order #{$order['order_no']}",
            'order' => $order,
            'role' => $role,
            'rider_location_enabled' => $role === 'customer'
                && $order['fulfillment_mode'] === 'internal'
                && in_array($order['status'], ['rider_assigned', 'picked_up'], true),
            'control_pass_active' => $role === 'customer' && $this->currentCustomerId() ? (new DeliveryControlService($this->db))->hasActivePass($id, (int)$this->currentCustomerId()) : false
        ]);
    }

    public function issue_control_pass($id)
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('orders');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf() || auth_role() !== 'customer') {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('orders/' . $id);
            return;
        }
        try {
            $token = (new DeliveryControlService($this->db))->issue($id, (int)$this->currentCustomerId());
            $url = base_url('delivery-control/' . rawurlencode($token));
            flash('success', 'Delivery control pass created. Copy this link now; it is shown only once: ' . $url);
        } catch (Throwable $e) {
            flash('error', 'Unable to create delivery control pass: ' . $e->getMessage());
        }
        redirect('orders/' . $id);
    }

    public function revoke_control_pass($id)
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('orders');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf() || auth_role() !== 'customer') {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('orders/' . $id);
            return;
        }
        $revoked = (new DeliveryControlService($this->db))->revoke($id, (int)$this->currentCustomerId());
        flash($revoked ? 'success' : 'error', $revoked ? 'Delivery control passes revoked.' : 'Order not found.');
        redirect('orders/' . $id);
    }

    /**
     * Cancel an order (allowed only before pickup)
     */
    public function cancel($id)
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('orders');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('orders/' . $id);
            return;
        }

        if (!verify_csrf()) {
            flash('error', 'Your session expired. Reload the page and try again.');
            redirect('orders/' . $id);
            return;
        }

        $role = auth_role();
        $customerId = ($role === 'customer') ? $this->currentCustomerId() : null;
        $order = $this->Order_model->get_by_id($id, $customerId);

        if (!$order) {
            flash('error', 'Order not found or permission denied.');
            redirect('orders');
            return;
        }

        $reason = trim($_POST['cancel_reason'] ?? 'Customer requested cancellation.');
        $actorType = $role;

        try {
            $this->call->library('OrderCancellationService');
            (new OrderCancellationService($this->Order_model))->cancel($id, $reason, $actorType, auth_id());
            try {
                $this->call->library('DeliveryNotificationService');
                (new DeliveryNotificationService($this->db))->dispatchPendingForOrder($id);
            } catch (Throwable $notificationError) {
                error_log('Cancellation status notification failed for order ' . $id . ': ' . $notificationError->getMessage());
            }
            flash('success', "Order #{$order['order_no']} has been cancelled.");
        } catch (\Throwable $e) {
            flash('error', 'Unable to cancel order: ' . $e->getMessage());
        }

        redirect('orders/' . $id);
    }
}
