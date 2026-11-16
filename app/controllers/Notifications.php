<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Notifications Controller
 * JSON feed for the in-app notification bell.
 */
class Notifications extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->library('api');
        $this->call->model('Notification_model');
        require_auth();
    }

    public function data(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->api->respond(['success' => false, 'error' => 'Method not allowed.'], 405);
            return;
        }

        $userId = (int)auth_id();
        $this->api->respond([
            'success' => true,
            'data' => [
                'notifications' => $this->Notification_model->get_recent($userId),
                'unreadCount' => $this->Notification_model->count_unread($userId)
            ]
        ]);
    }

    public function read(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !verify_csrf()) {
            $this->api->respond(['success' => false, 'error' => 'Invalid request or security token.'], 400);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $userId = (int)auth_id();
        if ($id > 0) {
            $this->Notification_model->mark_read($userId, $id);
        }

        $this->api->respond([
            'success' => true,
            'data' => ['unreadCount' => $this->Notification_model->count_unread($userId)]
        ]);
    }

    public function read_all(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !verify_csrf()) {
            $this->api->respond(['success' => false, 'error' => 'Invalid request or security token.'], 400);
            return;
        }

        $userId = (int)auth_id();
        $this->Notification_model->mark_all_read($userId);

        $this->api->respond([
            'success' => true,
            'data' => ['unreadCount' => 0]
        ]);
    }
}
