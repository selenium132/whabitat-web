ALTER TABLE calendar_events ADD COLUMN end_date DATE DEFAULT NULL AFTER event_date;
UPDATE calendar_events SET end_date = event_date WHERE end_date IS NULL;
