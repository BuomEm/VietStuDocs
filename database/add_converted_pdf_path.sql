-- Migration: Add converted_pdf_path column to documents table
-- This column stores the path to the PDF file converted from DOCX files
-- Run this SQL to update your database

-- Check and add converted_pdf_path column
SET @dbname = DATABASE();
SET @tablename = "documents";
SET @columnname = "converted_pdf_path";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column converted_pdf_path already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(255) DEFAULT NULL AFTER thumbnail")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for better performance
-- Note: If you get error "Duplicate key name 'idx_converted_pdf_path'", 
-- the index already exists - you can safely ignore that error.
CREATE INDEX idx_converted_pdf_path ON documents(converted_pdf_path);
