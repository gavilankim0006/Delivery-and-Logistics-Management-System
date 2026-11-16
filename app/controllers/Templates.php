<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Templates Controller
 * Saved delivery setups that customers reuse to create new draft orders.
 */
class Templates extends Controller
{
    private const MAX_TEMPLATES = 50;

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
        $this->call->model('Order_template_model');
        require_role(['customer']);
    }

    public function index(): void
    {
        $customerId = $this->currentCustomerId();
        if ($customerId === null) {
            http_response_code(403);
            return;
        }

        $templates = $this->Order_template_model->get_by_customer($customerId);
        $this->call->view('templates/index', [
            'page_title' => 'Order templates',
            'templates' => $templates,
            'template_count' => count($templates),
            'template_limit' => self::MAX_TEMPLATES
        ]);
    }

    /**
     * Save one of the customer's own orders (any status) as a reusable template.
     */
    public function save($orderId): void
    {
        if (!is_numeric($orderId) || (int)$orderId <= 0) {
            redirect('orders');
            return;
        }
        $orderId = (int)$orderId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || auth_role() !== 'customer' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('orders/' . $orderId);
            return;
        }

        $customerId = $this->currentCustomerId();
        $order = $customerId ? $this->Order_model->get_by_id($orderId, $customerId) : null;
        if (!$order) {
            http_response_code(404);
            flash('error', 'Order not found or permission denied.');
            redirect('orders');
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $name = 'Order ' . $order['order_no'];
        }
        if (mb_strlen($name) > 100) {
            flash('error', 'Template names must be 100 characters or fewer.');
            redirect('orders/' . $orderId);
            return;
        }
        if ($this->Order_template_model->count_for_customer($customerId) >= self::MAX_TEMPLATES) {
            flash('error', 'You reached the ' . self::MAX_TEMPLATES . '-template limit. Delete a template before adding another.');
            redirect('orders/' . $orderId);
            return;
        }

        try {
            $this->Order_template_model->create_from_order($customerId, $name, $order);
            flash('success', "Template \"{$name}\" saved. Reuse it anytime from your templates page.");
        } catch (Throwable $e) {
            error_log('Template save failed for order ' . $orderId . ': ' . $e->getMessage());
            flash('error', 'We could not save this template. Please try again.');
        }
        redirect('templates');
    }

    /**
     * Create a new draft order from a saved template.
     */
    public function use_template($id): void
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('templates');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || auth_role() !== 'customer' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('templates');
            return;
        }

        $customerId = $this->currentCustomerId();
        $template = $customerId ? $this->Order_template_model->get_for_customer($id, $customerId) : null;
        if (!$template) {
            http_response_code(404);
            flash('error', 'Template not found or permission denied.');
            redirect('templates');
            return;
        }

        try {
            $distanceKm = (float)$template['distance_km'];
            $weightKg = (float)$template['parcel_weight_kg'];
            $pickupLat = $template['pickup_latitude'] !== null ? (float)$template['pickup_latitude'] : null;
            $pickupLng = $template['pickup_longitude'] !== null ? (float)$template['pickup_longitude'] : null;
            $dropoffLat = $template['dropoff_latitude'] !== null ? (float)$template['dropoff_latitude'] : null;
            $dropoffLng = $template['dropoff_longitude'] !== null ? (float)$template['dropoff_longitude'] : null;

            if ($pickupLat === null || $pickupLng === null || $dropoffLat === null || $dropoffLng === null || $distanceKm <= 0) {
                $maps = $this->call->library('GoogleMapsService');
                $route = $maps->route((string)$template['pickup_address'], (string)$template['dropoff_address']);
                $distanceKm = (float)$route['distance_km'];
                $pickupLat = $pickupLat ?? (float)($route['start_location']['lat'] ?? 0);
                $pickupLng = $pickupLng ?? (float)($route['start_location']['lng'] ?? 0);
                $dropoffLat = $dropoffLat ?? (float)($route['end_location']['lat'] ?? 0);
                $dropoffLng = $dropoffLng ?? (float)($route['end_location']['lng'] ?? 0);
            }

            $feeCalc = calculate_delivery_fee($distanceKm, $weightKg);
            $created = $this->Order_model->create_order([
                'customer_id' => $customerId,
                'distance_km' => $distanceKm,
                'estimated_duration_min' => (int)ceil($distanceKm * 2.8),
                'delivery_fee' => $feeCalc['total_fee'],
                'actor_type' => 'customer',
                'actor_id' => auth_id(),
                'pickup' => [
                    'label' => 'Pickup Location',
                    'address_line' => $template['pickup_address'],
                    'unit_number' => $template['pickup_unit_number'],
                    'street' => $template['pickup_street'],
                    'barangay' => $template['pickup_barangay'],
                    'city' => $template['pickup_city'],
                    'province' => $template['pickup_province'],
                    'latitude' => $pickupLat,
                    'longitude' => $pickupLng,
                    'contact_name' => $template['pickup_contact_name'],
                    'contact_phone' => $template['pickup_contact_phone'],
                    'notes' => $template['pickup_notes']
                ],
                'dropoff' => [
                    'label' => 'Drop-off Location',
                    'address_line' => $template['dropoff_address'],
                    'unit_number' => $template['dropoff_unit_number'],
                    'street' => $template['dropoff_street'],
                    'barangay' => $template['dropoff_barangay'],
                    'city' => $template['dropoff_city'],
                    'province' => $template['dropoff_province'],
                    'latitude' => $dropoffLat,
                    'longitude' => $dropoffLng,
                    'contact_name' => $template['dropoff_contact_name'],
                    'contact_phone' => $template['dropoff_contact_phone'],
                    'notes' => $template['dropoff_notes']
                ],
                'parcel' => [
                    'description' => $template['parcel_description'],
                    'weight_kg' => $weightKg,
                    'size' => in_array($template['parcel_size'], ['S', 'M', 'L'], true) ? $template['parcel_size'] : 'M',
                    'is_fragile' => !empty($template['is_fragile']) ? 1 : 0
                ]
            ]);

            flash('success', "Draft #{$created['order_no']} created from template \"{$template['name']}\". Review the details and pay when ready.");
            redirect('orders/' . $created['order_id']);
        } catch (Throwable $e) {
            error_log('Template use failed for template ' . $id . ': ' . $e->getMessage());
            flash('error', $e instanceof InvalidArgumentException
                ? 'The saved addresses could no longer be resolved: ' . $e->getMessage()
                : 'We could not create an order from this template. Please try again.');
            redirect('templates');
        }
    }

    public function destroy($id): void
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('templates');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || auth_role() !== 'customer' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('templates');
            return;
        }

        $customerId = $this->currentCustomerId();
        $deleted = $customerId ? $this->Order_template_model->delete_for_customer($id, $customerId) : false;
        flash($deleted ? 'success' : 'error', $deleted ? 'Template deleted.' : 'Template not found.');
        redirect('templates');
    }
}
