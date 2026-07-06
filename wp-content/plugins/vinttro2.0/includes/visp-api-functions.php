<?php
/**
 * Register the custom route
 */
add_action('rest_api_init', function () {
    register_rest_route('vinttro/v1', '/create-member', [
        'methods'             => 'POST',
        'callback'            => 'vinttro_handle_crm_member',
        'permission_callback' => function () {
            return current_user_can('create_users');
        }
    ]);
});

/**
 * Recursive helper to sanitize multidimensional arrays dynamically
 * keeping data clean without knowing the field names in advance.
 */
function vinttro_sanitize_incoming_data($data) {
    if (!is_array($data)) {
        return sanitize_text_field($data);
    }
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = vinttro_sanitize_incoming_data($value);
        } else {
            // Contextual sanitization: Use esc_url_raw for URLs, standard sanitization for everything else
            if (strpos($key, 'url') !== false || strpos($key, 'image') !== false) {
                $data[$key] = esc_url_raw($value);
            } else {
                $data[$key] = sanitize_text_field($value);
            }
        }
    }
    return $data;
}

/**
 * The logic that runs when the API is hit
 */
function vinttro_handle_crm_member($request) {
    $params = $request->get_json_params();
    $email = sanitize_email($params['email'] ?? '');
    
    if (empty($email)) {
        return new WP_Error('missing_email', 'Email is required', ['status' => 400]);
    }

    $user = get_user_by('email', $email);

    if ($user) {
        $user_id = $user->ID;
        wp_update_user([
            'ID'         => $user_id,
            'first_name' => sanitize_text_field($params['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($params['last_name'] ?? ''),
        ]);
    } else {
        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'first_name' => sanitize_text_field($params['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($params['last_name'] ?? ''),
            'role'       => 'subscriber',
            'user_pass'  => wp_generate_password(12, true)
        ]);

        if (is_wp_error($user_id)) {
            return new WP_Error('create_failed', $user_id->get_error_message(), ['status' => 500]);
        }
    }

    // --- Dynamic Metadata Syncing ---
    // Top-level standalone keys
    $simple_meta_keys = ['mot_date', 'insurance_renewal', 'membership_status'];
    foreach ($simple_meta_keys as $key) {
        if (isset($params[$key])) {
            update_user_meta($user_id, 'vinttro_' . $key, sanitize_text_field($params[$key]));
        }
    }

    // --- Dynamic Array Syncing (Garage & Fleets) ---
    // WordPress accepts the whole vehicle/fleet array exactly as SuiteCRM formats it!
    if (isset($params['vehicles']) && is_array($params['vehicles'])) {
        $clean_garage = vinttro_sanitize_incoming_data($params['vehicles']);
        update_user_meta($user_id, 'vinttro_garage', $clean_garage);
    }

    if (isset($params['fleets']) && is_array($params['fleets'])) {
        $clean_fleets = vinttro_sanitize_incoming_data($params['fleets']);
        update_user_meta($user_id, 'vinttro_fleets', $clean_fleets);
    }

    if (isset($params['cover_rfqs']) && is_array($params['cover_rfqs'])) {
        $clean_rfqs = vinttro_sanitize_incoming_data($params['cover_rfqs']);
        update_user_meta($user_id, 'vinttro_cover_rfqs', $clean_rfqs);
    }

    return new WP_REST_Response(['status' => 'success', 'user_id' => $user_id], 200);
}