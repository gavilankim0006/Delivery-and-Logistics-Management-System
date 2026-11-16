<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Notification Model
 * In-app notification feed shown in the navigation bell.
 */
class Notification_model extends Model
{
    protected $table = 'user_notifications';
    protected $primary_key = 'id';
    protected $fillable = ['user_id', 'order_id', 'type', 'title', 'body', 'read_at'];

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function create_for_user(int $userId, string $type, string $title, string $body, ?int $orderId = null): void
    {
        $this->getDb()->table('user_notifications')->insert([
            'user_id' => $userId,
            'order_id' => $orderId,
            'type' => mb_substr(trim($type), 0, 40),
            'title' => mb_substr(trim($title), 0, 150),
            'body' => mb_substr(trim($body), 0, 500),
            'read_at' => null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function get_recent(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->getDb()->raw(
            "SELECT id, order_id, type, title, body, read_at, created_at
             FROM user_notifications
             WHERE user_id = ?
             ORDER BY id DESC LIMIT {$limit}",
            [$userId]
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function count_unread(int $userId): int
    {
        $stmt = $this->getDb()->raw(
            'SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
        return $stmt ? (int)$stmt->fetchColumn() : 0;
    }

    public function mark_read(int $userId, int $id): bool
    {
        $stmt = $this->getDb()->raw(
            'UPDATE user_notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $id, $userId]
        );
        return $stmt !== false;
    }

    public function mark_all_read(int $userId): int
    {
        $stmt = $this->getDb()->raw(
            'UPDATE user_notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $userId]
        );
        return $stmt ? $stmt->rowCount() : 0;
    }
}
