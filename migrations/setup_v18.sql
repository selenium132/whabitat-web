-- Increase avatar_url size to handle long LINE profile image URLs
ALTER TABLE users MODIFY COLUMN avatar_url VARCHAR(2048);
