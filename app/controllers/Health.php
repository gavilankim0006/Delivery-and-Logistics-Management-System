<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Health extends Controller
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (!defined('HEALTH_CHECK_DB_CONNECTION')) {
                define('HEALTH_CHECK_DB_CONNECTION', true);
            }
            $database = class_exists('Database', false) ? 'Database' : load_class('database', 'database');
            $db = $database::instance(null);
            if (!$db->health_check()) {
                throw new RuntimeException('Database probe failed.');
            }
            echo json_encode(['status' => 'ok', 'database' => 'connected']);
        } catch (Throwable $e) {
            error_log('Health check database probe failed: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['status' => 'error', 'database' => 'unavailable']);
        }
    }
}
