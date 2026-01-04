-- Add gender column to users table
ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'other') DEFAULT NULL AFTER faculty;
