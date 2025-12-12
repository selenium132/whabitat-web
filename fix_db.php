<?php
require_once 'config.php';
$pdo = getDB();
try {
    $sql = "CREATE TABLE IF NOT EXISTS event_admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_event_admin (event_id, user_id)
    )";
    $pdo->exec($sql);
    echo "Table 'event_admins' created successfully or already exists.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
