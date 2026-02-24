-- Migration: Add response schedule fields to events table
-- Run this SQL in phpMyAdmin

-- Add open_at column (when responses become available)
-- NULL means immediately available (default)
ALTER TABLE events ADD COLUMN open_at DATETIME DEFAULT NULL AFTER event_date;

-- Add close_at column (deadline for responses)
-- NULL means no deadline (default)
ALTER TABLE events ADD COLUMN close_at DATETIME DEFAULT NULL AFTER open_at;
