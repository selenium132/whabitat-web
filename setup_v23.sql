-- Track when users view surveys (for dashboard display)
CREATE TABLE IF NOT EXISTS survey_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_view (survey_id, user_id)
);
