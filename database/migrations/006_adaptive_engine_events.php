<?php
declare(strict_types=1);

return [
    'version' => '006_adaptive_engine_events',
    'up' => static function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS adaptive_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, course_id BIGINT UNSIGNED NULL, event_type VARCHAR(40) NOT NULL, payload JSON NULL, occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_adaptive_events_user_date (user_id, occurred_at), INDEX idx_adaptive_events_user_course (user_id, course_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL) ENGINE=InnoDB");
    },
];
