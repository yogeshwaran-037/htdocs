-- Inspected database: no message/chat table exists.
CREATE TABLE IF NOT EXISTS exchange_messages (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    submission_token CHAR(32) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_message_submission (request_id, sender_id, submission_token),
    KEY request_message_order (request_id, id),
    CONSTRAINT exchange_messages_request_fk FOREIGN KEY (request_id) REFERENCES exchange_requests(id) ON DELETE CASCADE,
    CONSTRAINT exchange_messages_sender_fk FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
