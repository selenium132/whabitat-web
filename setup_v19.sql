-- Add type column to events table to distinguish between Attendance Check (event) and Survey (survey)
-- Default is 'event' so existing records remain as Attendance Checks.
ALTER TABLE events ADD COLUMN type ENUM('event', 'survey') DEFAULT 'event' AFTER description;
