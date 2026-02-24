-- Migration: Add faculty field to users table
-- Run this SQL in phpMyAdmin

ALTER TABLE users ADD COLUMN faculty VARCHAR(100) DEFAULT NULL AFTER grade;
