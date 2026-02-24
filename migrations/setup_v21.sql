-- Fix gender ENUM to remove 'other' option (if already created with 'other')
ALTER TABLE users MODIFY COLUMN gender ENUM('male', 'female') DEFAULT NULL;
