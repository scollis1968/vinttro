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
    
    // ... [Previous User Creation/Update Logic goes here] ...
    // Assume $user_id is already defined from the Upsert logic

    // --- Handling the Vehicle Collection ---
    if (isset($params['vehicles']) && is_array($params['vehicles'])) {
        $garage_data = [];

        foreach ($params['vehicles'] as $vehicle) {
            $garage_data[] = [
                'make'       => sanitize_text_field($vehicle['make']),
                'model'      => sanitize_text_field($vehicle['model']),
                'reg'        => sanitize_text_field($vehicle['reg']),
                'mot_expiry' => sanitize_text_field($vehicle['mot_expiry']),
                'ins_expiry' => sanitize_text_field($vehicle['ins_expiry']),
                'image_url'  => esc_url_raw($vehicle['image_url'])
            ];
        }

        // This saves the entire array into one meta field
        update_user_meta($user_id, 'vinttro_garage', $garage_data);
    }

    // ... [Previous Email Logic goes here] ...

    return new WP_REST_Response([
        'status'  => 'success',
        'user_id' => $user_id,
        'message' => 'Member and garage data synced.'
    ], 200);
}