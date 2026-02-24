-- Add target_users column to events table for survey targeting
-- NULL = all users can see, otherwise JSON array of user IDs
ALTER TABLE events ADD COLUMN target_users TEXT DEFAULT NULL AFTER type;
