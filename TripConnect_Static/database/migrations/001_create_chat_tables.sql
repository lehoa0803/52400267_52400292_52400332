-- TripConnect chat migration
-- MariaDB 10.4+ / MySQL 8+
-- This file is intentionally not executed by the application.
-- Run it manually in phpMyAdmin after reviewing it.

CREATE TABLE IF NOT EXISTS conversations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_one_id INT(11) NOT NULL,
    user_two_id INT(11) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_message_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conversation_pair (user_one_id, user_two_id),
    KEY idx_conversations_user_one (user_one_id),
    KEY idx_conversations_user_two (user_two_id),
    CONSTRAINT fk_conversations_user_one
        FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversations_user_two
        FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_conversations_distinct_users CHECK (user_one_id <> user_two_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    conversation_id INT(11) NOT NULL,
    sender_id INT(11) NOT NULL,
    recipient_id INT(11) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_messages_conversation_created (conversation_id, created_at),
    KEY idx_messages_recipient_read (recipient_id, read_at),
    CONSTRAINT fk_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_recipient
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
