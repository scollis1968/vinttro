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
    // Get the JSON data from the request body
    $params = $request->get_json_params();
    
    $email      = sanitize_email($params['email']);
    $first_name = sanitize_text_field($params['first_name']);
    $last_name  = sanitize_text_field($params['last_name']);

    if (empty($email)) {
        return new WP_Error('no_email', 'Email is required', ['status' => 400]);
    }

    // 1. Check if user already exists
    if (email_exists($email)) {
        return new WP_REST_Response([
            'status'  => 'exists',
            'message' => 'User already has an account.'
        ], 200); // 200 because it's a "success" in logic, just no action needed
    }

    // 2. Create the User
    $user_id = wp_insert_user([
        'user_login' => $email, // Using email as username is standard for memberships
        'user_email' => $email,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'role'       => 'subscriber', // Or your specific membership role
        'user_pass'  => wp_generate_password(12, true) // Random password
    ]);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // 3. Trigger the "Activation" Email
    // 'both' sends notice to admin AND the "set password" email to the user.
    wp_new_user_notification($user_id, null, 'both');

    return new WP_REST_Response([
        'status'  => 'created',
        'user_id' => $user_id,
        'message' => 'User created and activation email sent.'
    ], 201);
}