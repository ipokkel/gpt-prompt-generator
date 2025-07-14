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
			<span class="gptpg-step-label"><?php esc_html_e( 'Code Snippets', 'gpt-prompt-generator' ); ?></span>
		</div>
		<div id="gptpg-step-indicator-3" class="gptpg-step-indicator">
			3
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
					<?php esc_html_e( 'Fetch Post Content', 'gpt-prompt-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
	
	<!-- Step 2: Code Snippets -->
	<div id="gptpg-step-2" class="gptpg-step">
		<h2 class="gptpg-step-title"><?php esc_html_e( 'Step 2: Verify Code Snippets', 'gpt-prompt-generator' ); ?></h2>
		
		<p id="gptpg-post-title-container">
			<?php esc_html_e( 'Post Title:', 'gpt-prompt-generator' ); ?> <strong id="gptpg-post-title"></strong>
		</p>
		
		<p><?php esc_html_e( 'The following GitHub/Gist links were found in the post. You can add, edit, or remove links as needed.', 'gpt-prompt-generator' ); ?></p>
		
		<div id="gptpg-no-snippets-found" class="gptpg-notification">
			<?php esc_html_e( 'No GitHub/Gist links were found in the post. You can add them manually below.', 'gpt-prompt-generator' ); ?>
		</div>
		
		<form id="gptpg-snippets-form">
			<div id="gptpg-snippets-container">
				<!-- Snippet rows will be added here dynamically -->
			</div>
			
			<button type="button" id="gptpg-add-snippet" class="button">
				<?php esc_html_e( 'Add Snippet URL', 'gpt-prompt-generator' ); ?>
			</button>
			
			<div class="gptpg-error-message"></div>
			<div class="gptpg-warning-message"></div>
			
			<div class="gptpg-form-buttons">
				<button type="button" class="button gptpg-form-nav-button prev" data-step="1">
					<?php esc_html_e( '← Back', 'gpt-prompt-generator' ); ?>
				</button>
				<button type="submit" class="button button-primary">
					<span class="gptpg-loading"></span>
					<?php esc_html_e( 'Process Snippets', 'gpt-prompt-generator' ); ?>
				</button>
			</div>
		</form>
	</div>
	
	<!-- Step 3: Generate Prompt -->
	<div id="gptpg-step-3" class="gptpg-step">
		<h2 class="gptpg-step-title"><?php esc_html_e( 'Step 3: Generate Prompt', 'gpt-prompt-generator' ); ?></h2>
		
		<p><?php esc_html_e( 'Click the button below to generate a prompt using the post content and code snippets.', 'gpt-prompt-generator' ); ?></p>
		
		<div class="gptpg-error-message"></div>
		
		<div class="gptpg-form-buttons">
			<button type="button" class="button gptpg-form-nav-button prev" data-step="2">
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
