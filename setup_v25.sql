-- Add is_archived column to events table
ALTER TABLE events ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0;
