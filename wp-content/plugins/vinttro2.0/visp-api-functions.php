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
    
    if (empty($email)) {
        return new WP_Error('missing_email', 'Email is required', ['status' => 400]);
    }

    $user = get_user_by('email', $email);
    $is_new_user = false;

    if ($user) {
        // --- CASE 1: User Exists, we will UPDATE ---
        $user_id = $user->ID;
        
        // Optional: Update basic info if it changed
        wp_update_user([
            'ID'         => $user_id,
            'first_name' => sanitize_text_field($params['first_name']),
            'last_name'  => sanitize_text_field($params['last_name']),
        ]);
    } else {
        // --- CASE 2: User doesn't exist, we will CREATE ---
        $is_new_user = true;
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
    }

    // --- CASE 3: Sync Metadata (Runs for BOTH new and existing users) ---
    if (isset($params['mot_date'])) {
        update_user_meta($user_id, 'vinttro_mot_date', sanitize_text_field($params['mot_date']));
    }
    if (isset($params['insurance_renewal'])) {
        update_user_meta($user_id, 'vinttro_insurance_renewal', sanitize_text_field($params['insurance_renewal']));
    }
    if (isset($params['membership_status'])) {
        update_user_meta($user_id, 'vinttro_membership_status', sanitize_text_field($params['membership_status']));
    }

    // --- CASE 4: Welcome Email (Only for NEW users) ---
    if ($is_new_user) {
        try {
            if (function_exists('wp_new_user_notification')) {
                wp_new_user_notification($user_id, null, 'both');
            }
        } catch (Exception $e) {
            return new WP_REST_Response([
                'status'  => 'user_created_email_failed',
                'user_id' => $user_id,
                'message' => $e->getMessage()
            ], 201);
        }
    }

    return new WP_REST_Response([
        'status'  => $is_new_user ? 'created' : 'updated',
        'user_id' => $user_id,
        'message' => $is_new_user ? 'Member created and notified.' : 'Member data updated.'
    ], 200);
}