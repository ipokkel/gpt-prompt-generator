<?php
// This is a temporary file containing just the verify_session function to be added to the class-gptpg-form-handler.php file

/**
 * Verify session validity.
 * This is a lightweight validation endpoint that checks if a session token is valid
 * without requiring the full database session to be valid.
 */
public static function verify_session() {
	// Check nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gptpg_form' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gpt-prompt-generator' ) ) );
	}
	
	// Check if session ID was provided
	if ( ! isset( $_POST['session_id'] ) || empty( $_POST['session_id'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid session ID.', 'gpt-prompt-generator' ) ) );
	}
	
	// Get the session ID
	$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ) );
	
	// First check if the lightweight transient token exists
	$token_valid = get_transient( 'gptpg_token_' . $session_id );
	
	if ( $token_valid ) {
		// Token is valid, no need to check the database
		wp_send_json_success( array( 
			'valid' => true,
			'message' => __( 'Session is valid.', 'gpt-prompt-generator' ) 
		) );
		return;
	}
	
	// If transient doesn't exist, check the database as fallback
	$post_data = GPTPG_Database::get_post_by_session( $session_id );
	
	if ( $post_data && !is_array($post_data) ) {
		// Session exists in database, create a new transient token
		$expiry_time = intval( get_option( 'gptpg_expiry_time', 3600 ) );
		set_transient( 'gptpg_token_' . $session_id, true, $expiry_time );
		
		wp_send_json_success( array( 
			'valid' => true,
			'message' => __( 'Session validated from database.', 'gpt-prompt-generator' ) 
		) );
	} else {
		// Session is invalid
		wp_send_json_error( array( 
			'message' => __( 'Session is no longer valid on the server.', 'gpt-prompt-generator' ),
			'error_type' => 'session_invalid' 
		) );
	}
}
