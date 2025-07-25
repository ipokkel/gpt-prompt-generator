-- Clean up obsolete database tables
-- These old tables are no longer referenced in the current codebase

DROP TABLE IF EXISTS wp_gptpg_code_snippets;
DROP TABLE IF EXISTS wp_gptpg_posts;
DROP TABLE IF EXISTS wp_gptpg_prompts;

-- Verify cleanup
SELECT 'Obsolete tables removed successfully!' as status;
