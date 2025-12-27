-- Migration: Add thumbnail and total_pages columns to documents table
-- Simple version - run this if the smart version doesn't work
-- If you get "Duplicate column" error, the columns already exist - that's fine!

ALTER TABLE documents 
ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL AFTER file_name;

ALTER TABLE documents 
ADD COLUMN total_pages INT DEFAULT 0 AFTER thumbnail;

-- Add indexes for better performance (optional, ignore errors if they exist)
CREATE INDEX idx_thumbnail ON documents(thumbnail);
CREATE INDEX idx_total_pages ON documents(total_pages);
