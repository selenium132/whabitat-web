-- Add time fields to calendar_events table
ALTER TABLE calendar_events 
ADD COLUMN start_time TIME DEFAULT NULL AFTER event_date,
ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time,
ADD COLUMN is_all_day BOOLEAN DEFAULT TRUE AFTER end_time;
