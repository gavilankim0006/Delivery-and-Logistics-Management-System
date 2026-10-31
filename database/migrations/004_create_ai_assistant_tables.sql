-- Migration 004: Create AI Assistant Conversations and Messages
CREATE TABLE IF NOT EXISTS `ai_conversations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `role` VARCHAR(32) NOT NULL DEFAULT 'guest',
    `title` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ai_conversations_session` (`session_id`),
    INDEX `idx_ai_conversations_user` (`user_id`),
    CONSTRAINT `fk_ai_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `sender` ENUM('user', 'assistant', 'system', 'tool') NOT NULL,
    `content` MEDIUMTEXT NOT NULL,
    `tool_calls` JSON NULL,
    `tool_call_id` VARCHAR(64) NULL,
    `tool_name` VARCHAR(64) NULL,
    `tokens` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ai_messages_conversation` (`conversation_id`),
    CONSTRAINT `fk_ai_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
