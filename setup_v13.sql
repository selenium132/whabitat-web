-- Migration: Add capacity field to events table
-- Run this SQL in phpMyAdmin

-- Add capacity column (max number of participants who can join)
-- NULL means unlimited (default)
ALTER TABLE events ADD COLUMN capacity INT DEFAULT NULL AFTER close_at;
