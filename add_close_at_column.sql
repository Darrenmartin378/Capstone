-- Add close_at column to question_sets table for automatic timer functionality
-- This column will store the timestamp when the question set should automatically close

ALTER TABLE question_sets ADD COLUMN close_at DATETIME NULL AFTER open_at;

-- Add index for better performance when checking expired question sets
CREATE INDEX idx_question_sets_close_at ON question_sets(close_at);

-- Update existing question sets to calculate close_at based on open_at + timer_minutes
UPDATE question_sets 
SET close_at = DATE_ADD(open_at, INTERVAL COALESCE(timer_minutes, 0) MINUTE)
WHERE open_at IS NOT NULL AND timer_minutes IS NOT NULL AND timer_minutes > 0;

