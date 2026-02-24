-- Migration: Add source and is_read columns to contact_messages
-- Run this SQL manually in phpMyAdmin or similar tool

-- Add source column to distinguish between contact and suggestion
ALTER TABLE contact_messages ADD COLUMN source VARCHAR(20) DEFAULT 'contact' AFTER message;

-- Add is_read column to track if admin has seen the message
ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER source;

-- Update existing messages from suggestion box (if any)
UPDATE contact_messages SET source = 'suggestion' WHERE name LIKE '%目安箱%';

-- Update existing messages to be marked as read (so only new ones show as unread)
UPDATE contact_messages SET is_read = 1;
