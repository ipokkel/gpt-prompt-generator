-- Fix wp_gptpg_unique_prompts table to add missing post_id column
-- This addresses the database error: Unknown column 'post_id' in 'field list'

-- Add post_id column to existing prompts table
ALTER TABLE wp_gptpg_unique_prompts 
ADD COLUMN post_id bigint(20) unsigned NOT NULL AFTER prompt_id;

-- Add index for post_id
ALTER TABLE wp_gptpg_unique_prompts 
ADD KEY post_id (post_id);

-- Add foreign key constraint (optional - comment out if wp_gptpg_unique_posts doesn't exist)
ALTER TABLE wp_gptpg_unique_prompts 
ADD FOREIGN KEY (post_id) REFERENCES wp_gptpg_unique_posts(post_id) ON DELETE CASCADE;

-- Verify the table structure
DESCRIBE wp_gptpg_unique_prompts;
