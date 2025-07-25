-- GPT Prompt Generator Plugin - Database Tables Creation Script
-- Run this script to create all required tables for the plugin

-- Table for storing unique posts
CREATE TABLE wp_gptpg_unique_posts (
    post_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    post_url varchar(2083) NOT NULL,
    post_title text NOT NULL,
    post_content longtext NOT NULL,
    post_content_markdown longtext NOT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    expires_at datetime DEFAULT NULL,
    PRIMARY KEY (post_id),
    UNIQUE KEY post_url (post_url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing unique code snippets
CREATE TABLE wp_gptpg_unique_snippets (
    snippet_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    snippet_url varchar(2083) NOT NULL,
    snippet_type varchar(50) NOT NULL,
    snippet_content longtext,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (snippet_id),
    UNIQUE KEY snippet_url (snippet_url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing unique prompts
CREATE TABLE wp_gptpg_unique_prompts (
    prompt_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    prompt_content longtext NOT NULL,
    prompt_hash varchar(32) GENERATED ALWAYS AS (MD5(prompt_content)) STORED,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (prompt_id),
    UNIQUE KEY prompt_hash (prompt_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for sessions/processes that link everything together
CREATE TABLE wp_gptpg_sessions (
    session_id varchar(255) NOT NULL,
    user_id bigint(20) unsigned DEFAULT NULL,
    post_id bigint(20) unsigned DEFAULT NULL,
    prompt_id bigint(20) unsigned DEFAULT NULL,
    created_at datetime NOT NULL,
    status varchar(50) DEFAULT 'pending',
    PRIMARY KEY (session_id),
    KEY user_id (user_id),
    KEY post_id (post_id),
    KEY prompt_id (prompt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for session to snippet mappings (many-to-many)
CREATE TABLE wp_gptpg_session_snippets (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(255) NOT NULL,
    snippet_id bigint(20) unsigned NOT NULL,
    is_user_edited tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY session_snippet (session_id, snippet_id),
    KEY session_id (session_id),
    KEY snippet_id (snippet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for session to prompt mappings (many-to-many)
CREATE TABLE wp_gptpg_session_prompts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(255) NOT NULL,
    prompt_id bigint(20) unsigned NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY session_prompt (session_id, prompt_id),
    KEY session_id (session_id),
    KEY prompt_id (prompt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep the old table for backward compatibility if it exists
CREATE TABLE IF NOT EXISTS wp_gptpg_code_snippets (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(255) NOT NULL,
    post_id bigint(20) unsigned NOT NULL,
    snippet_url varchar(2083) NOT NULL,
    snippet_type varchar(50) NOT NULL,
    snippet_content longtext,
    is_user_edited tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY session_id (session_id),
    KEY post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify tables were created
SELECT 'Tables created successfully!' as status;
