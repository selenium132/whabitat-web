-- MTG History entries
CREATE TABLE IF NOT EXISTS mtg_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    year_group INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert existing entry
INSERT INTO mtg_history (event_date, title, subtitle, description, image_path, year_group) VALUES
('2024-11-12', 'わびチューン', '〜3人の美食家を添えて〜', '3人のクセ強審査員（論理派・アホ・のりお）を攻略せよ！「おでんの具は大根か卵か？」「初デートはイタリアンか居酒屋か？」などをテーマに、各班で白熱の議論とプレゼンを行いました。', 'mtg_2024_11_12.png', 2025);
    