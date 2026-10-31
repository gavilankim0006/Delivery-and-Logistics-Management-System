<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * AiToolRegistry
 *
 * Exposes role-scoped functions/tools for the Gemini model.
 * Executes safe read operations on models and computes answers with strict role boundaries.
 */
class AiToolRegistry
{
    protected $db;
    protected string $role;
    protected ?int $userId;
    protected ?int $customerId;
    protected ?int $riderId;

    public function __construct($db = null, string $role = 'guest', ?int $userId = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
        $this->role = strtolower(trim($role));
        $this->userId = $userId;
        if ($this->db) {
            $this->resolveEntities();
        }
    }

    protected function resolveEntities(): void
    {
        $this->customerId = null;
        $this->riderId = null;

        if ($this->userId !== null) {
            if ($this->role === 'customer') {
                $stmt = $this->db->raw('SELECT id FROM customers WHERE user_id = ? LIMIT 1', [$this->userId]);
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                $this->customerId = $row ? (int)$row['id'] : null;
            } elseif ($this->role === 'rider') {
                $stmt = $this->db->raw('SELECT id FROM riders WHERE user_id = ? LIMIT 1', [$this->userId]);
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                $this->riderId = $row ? (int)$row['id'] : null;
            }
        }
    }

    /**
     * Get system prompt customized specifically for the authenticated role
     */
    public function getSystemPrompt(): string
    {
        $base = "You are Dispatch AI, an intelligent operations and support assistant for the Delivery & Logistics platform. Keep responses concise, polite, professional, and directly actionable. Format with clean Markdown.";

        switch ($this->role) {
            case 'admin':
                return $base . "\n\nROLE: SYSTEM ADMINISTRATOR. You have high-level analytical visibility over system health, users, riders, orders, and dispatch logs. Help the administrator monitor KPIs, identify bottlenecks, review rider coverage, and inspect audit logs. Be precise with IDs and statuses.";

            case 'dispatcher':
                return $base . "\n\nROLE: DISPATCH OPERATOR. You assist with real-time fleet operations, pending order assignments, delayed deliveries, and rider statuses. Help prioritize urgent deliveries, advise on reassignments, and monitor delivery stages (draft -> pending_payment -> paid -> booking -> rider_assigned -> picked_up -> delivered).";

            case 'rider':
                return $base . "\n\nROLE: RIDER CO-PILOT. You assist the delivery rider on duty. Be brief, practical, safety-conscious. Help them check current assigned task, pickup/dropoff addresses, recipient contact details, package weight/fragility notes, and proof-of-delivery guidelines.";

            case 'customer':
                return $base . "\n\nROLE: CUSTOMER SUPPORT SPECIALIST. You assist the customer with their own orders, delivery estimates, tracking status, and FAQs. Never reveal other customers' orders, internal rider phone numbers, or internal pricing formulas. Be reassuring and helpful.";

            case 'guest':
            default:
                return $base . "\n\nROLE: PUBLIC GUEST CONCIERGE. You assist visitors with public order tracking (given an order number), service capabilities, delivery coverage, and account registration instructions. You have zero access to private accounts or internal fleet data.";
        }
    }

    /**
     * Get tool definitions for OpenAI function calling according to role
     */
    public function getTools(): array
    {
        $tools = [];

        // All users (including guests) can track by order number
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'track_order',
                'description' => 'Look up the live status, timeline, and delivery status of an order using its order number (e.g. DLV-20261026-0001).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_no' => [
                            'type' => 'string',
                            'description' => 'The unique order number (e.g., DLV-20261026-0001)'
                        ]
                    ],
                    'required' => ['order_no']
                ]
            ]
        ];

        // Customer specific tools
        if ($this->role === 'customer') {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'list_my_recent_orders',
                    'description' => 'List the recent orders placed by the current logged-in customer.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum number of orders to return (default 5)'
                            ]
                        ]
                    ]
                ]
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'estimate_delivery_fee',
                    'description' => 'Get standard estimated delivery fee based on estimated distance in kilometers.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'distance_km' => [
                                'type' => 'number',
                                'description' => 'Distance in kilometers'
                            ],
                            'vehicle_type' => [
                                'type' => 'string',
                                'enum' => ['motorcycle', 'sedan', 'van'],
                                'description' => 'Type of vehicle needed'
                            ]
                        ],
                        'required' => ['distance_km']
                    ]
                ]
            ];
        }

        // Rider specific tools
        if ($this->role === 'rider') {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_my_active_assignment',
                    'description' => 'Get the current active order assigned to this rider, including pickup and dropoff details.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new stdClass()
                    ]
                ]
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_rider_instructions',
                    'description' => 'Get specific parcel and contact instructions for an assigned order.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_id' => [
                                'type' => 'integer',
                                'description' => 'Order ID'
                            ]
                        ],
                        'required' => ['order_id']
                    ]
                ]
            ];
        }

        // Dispatcher / Admin tools
        if (in_array($this->role, ['dispatcher', 'admin'], true)) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_fleet_status_summary',
                    'description' => 'Get summary counts of orders by status and active rider availability.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new stdClass()
                    ]
                ]
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'find_pending_or_delayed_orders',
                    'description' => 'Find orders that need attention (e.g. paid but unassigned, booking, or failed).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Number of orders to inspect (default 10)'
                            ]
                        ]
                    ]
                ]
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'list_available_riders',
                    'description' => 'List currently available internal riders ready for assignment.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new stdClass()
                    ]
                ]
            ];
        }

        return $tools;
    }

    /**
     * Execute a tool call safely
     */
    public function execute(string $name, array $args = []): array
    {
        try {
            switch ($name) {
                case 'track_order':
                    return $this->toolTrackOrder($args['order_no'] ?? '');

                case 'list_my_recent_orders':
                    return $this->toolListMyRecentOrders((int)($args['limit'] ?? 5));

                case 'estimate_delivery_fee':
                    return $this->toolEstimateDeliveryFee((float)($args['distance_km'] ?? 0), $args['vehicle_type'] ?? 'motorcycle');

                case 'get_my_active_assignment':
                    return $this->toolGetMyActiveAssignment();

                case 'get_rider_instructions':
                    return $this->toolGetRiderInstructions((int)($args['order_id'] ?? 0));

                case 'get_fleet_status_summary':
                    return $this->toolGetFleetStatusSummary();

                case 'find_pending_or_delayed_orders':
                    return $this->toolFindPendingOrDelayedOrders((int)($args['limit'] ?? 10));

                case 'list_available_riders':
                    return $this->toolListAvailableRiders();

                default:
                    return ['error' => "Unknown or unauthorized function: {$name}"];
            }
        } catch (\Throwable $e) {
            return ['error' => 'Tool execution failed: ' . $e->getMessage()];
        }
    }

    protected function toolTrackOrder(string $orderNo): array
    {
        $orderNo = trim($orderNo);
        if ($orderNo === '') {
            return ['error' => 'Please provide an order number (e.g. DLV-20261026-0001).'];
        }

        $stmt = $this->db->raw(
            'SELECT o.id, o.order_no, o.status, o.created_at, o.distance_km, o.delivery_fee,
                    pa.address_line AS pickup_address,
                    da.address_line AS dropoff_address,
                    r.name AS rider_name,
                    p.description AS parcel_description
             FROM orders o
             INNER JOIN addresses pa ON o.pickup_address_id = pa.id
             INNER JOIN addresses da ON o.dropoff_address_id = da.id
             LEFT JOIN parcels p ON p.order_id = o.id
             LEFT JOIN riders r ON o.rider_id = r.id
             WHERE o.order_no = ? AND o.deleted_at IS NULL LIMIT 1',
            [$orderNo]
        );
        $order = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$order) {
            return ['found' => false, 'message' => "Order {$orderNo} was not found."];
        }

        // Fetch timeline history
        $timelineStmt = $this->db->raw(
            'SELECT to_status, notes, created_at FROM deliveries WHERE order_id = ? ORDER BY id ASC',
            [(int)$order['id']]
        );
        $timeline = $timelineStmt ? $timelineStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return [
            'found' => true,
            'order_no' => $order['order_no'],
            'status' => $order['status'],
            'pickup_address' => $order['pickup_address'],
            'dropoff_address' => $order['dropoff_address'],
            'parcel' => $order['parcel_description'],
            'assigned_rider' => $order['rider_name'] ? $order['rider_name'] : 'Not yet assigned',
            'created_at' => $order['created_at'],
            'timeline' => $timeline
        ];
    }

    protected function toolListMyRecentOrders(int $limit): array
    {
        if ($this->customerId === null) {
            return ['error' => 'No customer profile linked to your account.'];
        }

        $limit = max(1, min(10, $limit));
        $stmt = $this->db->raw(
            "SELECT o.id, o.order_no, o.status, o.delivery_fee, o.created_at,
                    da.address_line AS destination
             FROM orders o
             INNER JOIN addresses da ON o.dropoff_address_id = da.id
             WHERE o.customer_id = ? AND o.deleted_at IS NULL
             ORDER BY o.id DESC LIMIT {$limit}",
            [$this->customerId]
        );
        $orders = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return [
            'total' => count($orders),
            'orders' => $orders
        ];
    }

    protected function toolEstimateDeliveryFee(float $km, string $vehicle): array
    {
        $baseFee = 50.0;
        $perKm = 10.0;

        // Fetch settings if available
        $settingsStmt = $this->db->raw("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('base_delivery_fee', 'per_km_rate')");
        if ($settingsStmt) {
            foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                if ($s['setting_key'] === 'base_delivery_fee') $baseFee = (float)$s['setting_value'];
                if ($s['setting_key'] === 'per_km_rate') $perKm = (float)$s['setting_value'];
            }
        }

        $multiplier = 1.0;
        if ($vehicle === 'sedan') $multiplier = 1.4;
        if ($vehicle === 'van') $multiplier = 2.0;

        $fee = ($baseFee + ($km * $perKm)) * $multiplier;

        return [
            'distance_km' => round($km, 2),
            'vehicle_type' => $vehicle,
            'base_rate' => $baseFee,
            'per_km_rate' => $perKm,
            'estimated_total_fee' => round($fee, 2),
            'currency' => 'PHP'
        ];
    }

    protected function toolGetMyActiveAssignment(): array
    {
        if ($this->riderId === null) {
            return ['error' => 'You are not registered as an active rider in the system.'];
        }

        $stmt = $this->db->raw(
            "SELECT o.id, o.order_no, o.status, o.distance_km,
                    pa.address_line AS pickup_address, pa.contact_name AS pickup_name, pa.contact_phone AS pickup_phone, pa.notes AS pickup_notes,
                    da.address_line AS dropoff_address, da.contact_name AS dropoff_name, da.contact_phone AS dropoff_phone, da.notes AS dropoff_notes,
                    p.description AS parcel_description, p.weight_kg, p.is_fragile
             FROM rider_assignments ra
             INNER JOIN orders o ON ra.order_id = o.id
             INNER JOIN addresses pa ON o.pickup_address_id = pa.id
             INNER JOIN addresses da ON o.dropoff_address_id = da.id
             LEFT JOIN parcels p ON p.order_id = o.id
             WHERE ra.rider_id = ? AND ra.released_at IS NULL AND o.status IN ('rider_assigned', 'picked_up')
             ORDER BY ra.id DESC LIMIT 1",
            [$this->riderId]
        );
        $assignment = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$assignment) {
            return ['has_active_assignment' => false, 'message' => 'No active deliveries currently assigned to you.'];
        }

        return [
            'has_active_assignment' => true,
            'order' => $assignment
        ];
    }

    protected function toolGetRiderInstructions(int $orderId): array
    {
        if ($this->riderId === null) {
            return ['error' => 'Not a registered rider.'];
        }

        $stmt = $this->db->raw(
            "SELECT o.id, o.order_no, o.status,
                    pa.notes AS pickup_instructions, da.notes AS dropoff_instructions,
                    p.description, p.weight_kg, p.size, p.is_fragile
             FROM orders o
             INNER JOIN addresses pa ON o.pickup_address_id = pa.id
             INNER JOIN addresses da ON o.dropoff_address_id = da.id
             LEFT JOIN parcels p ON p.order_id = o.id
             WHERE o.id = ? AND o.rider_id = ? AND o.deleted_at IS NULL LIMIT 1",
            [$orderId, $this->riderId]
        );
        $order = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$order) {
            return ['error' => 'Order not found or not assigned to you.'];
        }

        return [
            'order_no' => $order['order_no'],
            'parcel' => [
                'description' => $order['description'],
                'weight_kg' => $order['weight_kg'],
                'size' => $order['size'],
                'is_fragile' => (bool)$order['is_fragile']
            ],
            'pickup_instructions' => $order['pickup_instructions'] ?: 'Standard pickup',
            'dropoff_instructions' => $order['dropoff_instructions'] ?: 'Standard doorstep delivery'
        ];
    }

    protected function toolGetFleetStatusSummary(): array
    {
        if (!in_array($this->role, ['dispatcher', 'admin'], true)) {
            return ['error' => 'Unauthorized'];
        }

        // Order counts by status
        $statusCounts = $this->db->raw(
            "SELECT status, COUNT(*) as count FROM orders WHERE deleted_at IS NULL GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        // Rider availability
        $riderCounts = $this->db->raw(
            "SELECT status, COUNT(*) as count FROM riders WHERE deleted_at IS NULL GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'orders_by_status' => $statusCounts ?: [],
            'riders_by_status' => $riderCounts ?: []
        ];
    }

    protected function toolFindPendingOrDelayedOrders(int $limit): array
    {
        if (!in_array($this->role, ['dispatcher', 'admin'], true)) {
            return ['error' => 'Unauthorized'];
        }

        $limit = max(1, min(25, $limit));
        $stmt = $this->db->raw(
            "SELECT o.id, o.order_no, o.status, o.fulfillment_mode, o.created_at,
                    pa.address_line AS pickup, da.address_line AS dropoff
             FROM orders o
             INNER JOIN addresses pa ON o.pickup_address_id = pa.id
             INNER JOIN addresses da ON o.dropoff_address_id = da.id
             WHERE o.status IN ('paid', 'booking', 'failed') AND o.deleted_at IS NULL
             ORDER BY o.created_at ASC LIMIT {$limit}"
        );
        $orders = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return [
            'count' => count($orders),
            'actionable_orders' => $orders
        ];
    }

    protected function toolListAvailableRiders(): array
    {
        if (!in_array($this->role, ['dispatcher', 'admin'], true)) {
            return ['error' => 'Unauthorized'];
        }

        $stmt = $this->db->raw(
            "SELECT r.id, r.name, r.phone, r.vehicle_type, r.plate_number
             FROM riders r
             WHERE r.status = 'available' AND r.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM rider_assignments ra WHERE ra.rider_id = r.id AND ra.released_at IS NULL
               )
             ORDER BY r.name ASC"
        );
        $riders = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return [
            'count' => count($riders),
            'available_riders' => $riders
        ];
    }
}
