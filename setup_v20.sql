-- Add gender column to users table
ALTER TABLE users ADD COLUMN gender ENUM('male', 'female') DEFAULT NULL AFTER faculty;
