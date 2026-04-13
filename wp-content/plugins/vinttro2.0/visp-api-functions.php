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

// --- Handling the Vehicle Collection ---
    if (isset($params['vehicles']) && is_array($params['vehicles'])) {
        $garage_data = [];

        foreach ($params['vehicles'] as $vehicle) {
            $garage_data[] = [
                'fleet'      => sanitize_text_field($vehicle['fleet']),
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

    // --- Handling the Fleet data ---
if (isset($params['fleets']) && is_array($params['fleets'])) {
    $fleet_data = [];

    foreach ($params['fleets'] as $fleet) {
        // 1. Initialize the array INSIDE the loop so it resets for every fleet
        $fleet_vehicles = []; 

        if (isset($fleet['vehicles']) && is_array($fleet['vehicles'])) {
            foreach ($fleet['vehicles'] as $vehicle) {
                $fleet_vehicles[] = [
                    // Added ?? '' because these keys might be missing in some JSON objects
                    'fleet'      => sanitize_text_field($vehicle['fleet'] ?? ''), 
                    'make'       => sanitize_text_field($vehicle['make'] ?? ''),
                    'model'      => sanitize_text_field($vehicle['model'] ?? ''),
                    'reg'        => sanitize_text_field($vehicle['reg'] ?? ''),
                    'mot_expiry' => sanitize_text_field($vehicle['mot_expiry'] ?? ''),
                    'date_next_service' => sanitize_text_field($vehicle['date_next_service'] ?? ''),
                    'date_last_service' => sanitize_text_field($vehicle['date_last_service'] ?? ''),
                    'date_last_check' => sanitize_text_field($vehicle['date_last_check'] ?? ''),
                    'ins_expiry' => sanitize_text_field($vehicle['ins_expiry'] ?? ''),
                    'image_url'  => esc_url_raw($vehicle['image_url'] ?? ''),
                    // Note: Your test data has extra fields like 'driver_name'. 
                    // Add them here if you want to save them!
                    'driver_name' => sanitize_text_field($vehicle['driver_name'] ?? ''),
                ];
            }
        }

        $fleet_data[] = [
            'name'     => sanitize_text_field($fleet['name'] ?? ''),
            // ERROR FIXED HERE: Removed sanitize_text_field() from the array
            'vehicles' => $fleet_vehicles 
        ];
    }

    // WordPress update_user_meta automatically handles (serializes) arrays
    update_user_meta($user_id, 'vinttro_fleets', $fleet_data);
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
        'status'  => 'success',
        'user_id' => $user_id,
        'message' => 'Member and garage data synced.'
    ], 200);
}