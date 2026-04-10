<?php
/**
 * Register the custom route
 */
add_action('rest_api_init', function () {
    register_rest_route('vinttro/v1', '/create-member', [
        'methods'             => 'POST',
        'callback'            => 'vinttro_handle_crm_member',
        'permission_callback' => function () {
            // This ensures only users with 'create_users' capability (like Admins) 
            // can access this endpoint via the API.
            return current_user_can('create_users');
        }
    ]);
});

/**
 * The logic that runs when the API is hit
 */
function vinttro_handle_crm_member($request) {
    $params = $request->get_json_params();
    $email = sanitize_email($params['email']);

    // 1. Double check exists (to avoid duplicate errors)
    if (email_exists($email)) {
        return new WP_REST_Response(['message' => 'User exists'], 200);
    }

    // 2. Attempt user creation
    $user_id = wp_insert_user([
        'user_login' => $email,
        'user_email' => $email,
        'first_name' => sanitize_text_field($params['first_name']),
        'last_name'  => sanitize_text_field($params['last_name']),
        'role'       => 'subscriber',
        'user_pass'  => wp_generate_password(12, true)
    ]);

    if (is_wp_error($user_id)) {
        return new WP_Error('create_failed', $user_id->get_error_message(), ['status' => 500]);
    }

    // Update metadata sent from SuiteCRM
    if (isset($params['mot_date'])) {
        update_user_meta($user_id, 'vinttro_mot_date', sanitize_text_field($params['mot_date']));
    }
    if (isset($params['insurance_renewal'])) {
        update_user_meta($user_id, 'vinttro_insurance_renewal', sanitize_text_field($params['insurance_renewal']));
    }
    if (isset($params['membership_status'])) {
        update_user_meta($user_id, 'vinttro_membership_status', sanitize_text_field($params['membership_status']));
    }

    // 3. The "Danger Zone": Email and Hooks
    // Wrap this in a check to see if it's the source of the crash
    try {
        if (function_exists('wp_new_user_notification')) {
            wp_new_user_notification($user_id, null, 'both');
        }
    } catch (Exception $e) {
        // If email fails, we still want to know the user was created
        return new WP_REST_Response([
            'status' => 'user_created_email_failed',
            'user_id' => $user_id,
            'error' => $e->getMessage()
        ], 201);
    }

    return new WP_REST_Response([
        'status' => 'success',
        'user_id' => $user_id
    ], 201);
}