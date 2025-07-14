<?php
/**
 * Multi-step form template
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="gptpg-form-container">
	<!-- Step indicators -->
	<div class="gptpg-steps">
		<div id="gptpg-step-indicator-1" class="gptpg-step-indicator active">
			1
			<span class="gptpg-step-label"><?php esc_html_e( 'Submit URL', 'gpt-prompt-generator' ); ?></span>
		</div>
		<div id="gptpg-step-indicator-2" class="gptpg-step-indicator">
			2
			<span class="gptpg-step-label"><?php esc_html_e( 'Paste Markdown', 'gpt-prompt-generator' ); ?></span>
		</div>
		<div id="gptpg-step-indicator-3" class="gptpg-step-indicator">
			3
			<span class="gptpg-step-label"><?php esc_html_e( 'Code Snippets', 'gpt-prompt-generator' ); ?></span>
		</div>
		<div id="gptpg-step-indicator-4" class="gptpg-step-indicator">
			4
			<span class="gptpg-step-label"><?php esc_html_e( 'Generate Prompt', 'gpt-prompt-generator' ); ?></span>
		</div>
	</div>
	
	<!-- Step 1: Submit URL -->
	<div id="gptpg-step-1" class="gptpg-step">
		<h2 class="gptpg-step-title"><?php esc_html_e( 'Step 1: Enter Post URL', 'gpt-prompt-generator' ); ?></h2>
		
		<p><?php esc_html_e( 'Enter the URL of a Tooltip Recipe Post that you want to generate a prompt for.', 'gpt-prompt-generator' ); ?></p>
		
		<form id="gptpg-url-form">
			<div class="gptpg-form-row">
				<label for="gptpg-post-url" class="gptpg-label"><?php esc_html_e( 'Post URL:', 'gpt-prompt-generator' ); ?></label>
				<input type="url" id="gptpg-post-url" class="gptpg-input" placeholder="https://example.com/tooltip-recipe-post" required>
			</div>
			
			<div class="gptpg-error-message"></div>
			
			<div class="gptpg-form-buttons">
				<div></div> <!-- Empty div for flex spacing -->
				<button type="submit" class="button button-primary">
					<span class="gptpg-loading"></span>
					<?php esc_html_e( 'Continue to Next Step', 'gpt-prompt-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
	
	<!-- Step 2: Paste Markdown Content -->
	<div id="gptpg-step-2" class="gptpg-step">
		<h2 class="gptpg-step-title"><?php esc_html_e( 'Step 2: Paste Post Content in Markdown', 'gpt-prompt-generator' ); ?></h2>
		
		<p id="gptpg-post-url-display">
			<?php esc_html_e( 'Post URL:', 'gpt-prompt-generator' ); ?> <strong id="gptpg-display-url"></strong>
		</p>

		<p><?php esc_html_e( 'Please paste the content of the post in Markdown format.', 'gpt-prompt-generator' ); ?></p>
		
		<details id="gptpg-browser-extensions" class="gptpg-notification">
			<summary><strong><?php esc_html_e( 'Need help converting to Markdown?', 'gpt-prompt-generator' ); ?></strong></summary>
			<p><?php esc_html_e( 'Use one of these browser extensions to convert the page to Markdown:', 'gpt-prompt-generator' ); ?></p>
			
			<h4><?php esc_html_e( 'Chrome Extensions', 'gpt-prompt-generator' ); ?></h4>
			<ul>
				<li><a href="https://chromewebstore.google.com/detail/webpage-to-markdown/ajeinonckioeekcfanjndliandidilid" target="_blank"><?php esc_html_e( 'Webpage to Markdown', 'gpt-prompt-generator' ); ?></a></li>
				<li><a href="https://chromewebstore.google.com/detail/markdownload-markdown-web/pcmpcfapbekmbjjkdalcgopdkipoggdi" target="_blank"><?php esc_html_e( 'MarkDownload - Markdown Web Clipper', 'gpt-prompt-generator' ); ?></a></li>
			</ul>
			
			<h4><?php esc_html_e( 'Firefox Extensions', 'gpt-prompt-generator' ); ?></h4>
			<ul>
				<li><a href="https://addons.mozilla.org/en-US/firefox/addon/markdownload/" target="_blank"><?php esc_html_e( 'MarkDownload - Markdown Web Clipper', 'gpt-prompt-generator' ); ?></a></li>
				<li><a href="https://addons.mozilla.org/en-US/firefox/addon/llmfeeder/" target="_blank"><?php esc_html_e( 'LLMFeeder - Webpage to Markdown', 'gpt-prompt-generator' ); ?></a></li>
			</ul>
			
			<h4><?php esc_html_e( 'Edge Extensions', 'gpt-prompt-generator' ); ?></h4>
			<ul>
				<li><a href="https://microsoftedge.microsoft.com/addons/detail/cbbdkefgbfifiljnnklfhcnlmpglpd" target="_blank"><?php esc_html_e( 'Copy as Markdown', 'gpt-prompt-generator' ); ?></a></li>
			</ul>
			
			<h4><?php esc_html_e( 'Safari Extensions', 'gpt-prompt-generator' ); ?></h4>
			<p><?php esc_html_e( 'For Safari, consider Obsidian Web Clipper or ToMarkdown extensions (available on GitHub).', 'gpt-prompt-generator' ); ?></p>
			
			<p><a href="<?php echo esc_url( plugins_url( '/briefs/browser-extension-list.md', dirname( dirname( __FILE__ ) ) ) ); ?>" target="_blank"><?php esc_html_e( 'View complete list of extensions', 'gpt-prompt-generator' ); ?></a></p>
		</details>
		
		<form id="gptpg-markdown-form">
			<div class="gptpg-form-row">
				<label for="gptpg-markdown-content" class="gptpg-label"><?php esc_html_e( 'Markdown Content:', 'gpt-prompt-generator' ); ?></label>
				<textarea id="gptpg-markdown-content" class="gptpg-textarea" rows="15" placeholder="<?php esc_attr_e( 'Paste the post content in Markdown format here', 'gpt-prompt-generator' ); ?>" required></textarea>
			</div>
			
			<div class="gptpg-error-message"></div>
			
			<div class="gptpg-form-buttons">
				<button type="button" class="button gptpg-form-nav-button prev" data-step="1">
					<?php esc_html_e( '← Back', 'gpt-prompt-generator' ); ?>
				</button>
				<button type="submit" class="button button-primary">
					<span class="gptpg-loading"></span>
					<?php esc_html_e( 'Process Content & Continue', 'gpt-prompt-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
	
	<!-- Step 3: Code Snippets Only -->
	<div id="gptpg-step-3" class="gptpg-step">
		<h2 class="gptpg-step-title"><?php esc_html_e( 'Step 3: Add Code Snippets', 'gpt-prompt-generator' ); ?></h2>
		
		<p><?php esc_html_e( 'Add GitHub/Gist links for code snippets to include in the prompt. You can add, edit, or remove links as needed.', 'gpt-prompt-generator' ); ?></p>
		
		<form id="gptpg-snippets-form">
			<div id="gptpg-snippets-container">
				<!-- Snippet rows will be added here dynamically -->
			</div>
			
			<button type="button" id="gptpg-add-snippet" class="button">
				<?php esc_html_e( 'Add Snippet URL', 'gpt-prompt-generator' ); ?>
			</button>
			
			<div class="gptpg-error-message"></div>
			<div class="gptpg-warning-message"></div>
			
			<div class="gptpg-form-buttons snippet-buttons">
				<button type="button" class="button gptpg-form-nav-button prev" data-step="2">
					<?php esc_html_e( '← Back', 'gpt-prompt-generator' ); ?>
				</button>
				<button type="submit" id="gptpg-process-snippets" class="button button-primary">
					<span class="gptpg-loading"></span>
					<?php esc_html_e( 'Process Snippets', 'gpt-prompt-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
	
	<!-- Step 4: Generate Prompt -->
	<div id="gptpg-step-4" class="gptpg-step">
		<h2 class="gptpg-step-title"><?php esc_html_e( 'Step 4: Generate Prompt', 'gpt-prompt-generator' ); ?></h2>
		
		<p><?php esc_html_e( 'Click the button below to generate a prompt using the post content and code snippets.', 'gpt-prompt-generator' ); ?></p>
		
		<div class="gptpg-form-buttons">
			<button type="button" class="button gptpg-form-nav-button prev" data-step="3">
				<?php esc_html_e( '← Back', 'gpt-prompt-generator' ); ?>
			</button>
			<button type="button" id="gptpg-generate-prompt" class="button button-primary">
				<span class="gptpg-loading"></span>
				<?php esc_html_e( 'Generate Prompt', 'gpt-prompt-generator' ); ?>
			</button>
		</div>
		
		<div id="gptpg-prompt-container" class="gptpg-prompt-container">
			<h3><?php esc_html_e( 'Generated Prompt:', 'gpt-prompt-generator' ); ?></h3>
			<textarea id="gptpg-generated-prompt" class="gptpg-generated-prompt" readonly></textarea>
			
			<div class="gptpg-form-buttons">
				<button type="button" id="gptpg-start-new" class="button">
					<?php esc_html_e( 'Start New', 'gpt-prompt-generator' ); ?>
				</button>
				<button type="button" id="gptpg-copy-prompt" class="button button-primary">
					<?php esc_html_e( 'Copy to Clipboard', 'gpt-prompt-generator' ); ?>
				</button>
			</div>
		</div>
	</div>
	</div>
</div>
