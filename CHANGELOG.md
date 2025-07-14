# Changelog
All notable changes to the GPT Prompt Generator plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.3] - 2025-07-14
### Added
- Added support for [link_old_post] placeholder in prompt templates
- Updated [link_code_recipe] to only include links to code snippets, not the code content itself

## [0.0.2] - 2025-07-14
### Added
- Implemented markdown content cleanup to strip out author information, footers, and irrelevant content
- Created 4-step form workflow separating code snippets (Step 3) from prompt generation (Step 4)
- Added post title extraction from markdown content to support [post_title] placeholder in prompt templates

### Changed
- Updated step navigation in JavaScript to support 4-step workflow
- Improved CSS for step indicators to better accommodate 4 steps

## [0.0.1] - 2025-07-14
### Fixed
- Browser extension suggestions now always visible in Step 2 by removing conditional browser detection logic
- Fixed PHP syntax errors in multi-step form template
- Updated CSS to prevent hiding browser extension suggestions by removing `display: none` from `.gptpg-notification`
- Made Markdown content textarea full width in Step 2 for better usability
- Fixed JavaScript error `gptpg_vars is not defined` by removing duplicate script localization in shortcode class
- Fixed PHP fatal error from missing `extract_github_links()` method by replacing it with an empty array (link extraction now optional in new workflow)

### Changed
- Browser extension suggestions now display in a collapsible `<details>` element showing all extensions
- Centralized JavaScript variable localization in the main plugin file
- Script variables are now consistently available across the entire plugin
