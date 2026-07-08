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
    register_rest_route('vinttro/v1', '/outbound-call', array(
        'methods' => 'POST',
        'callback' => 'vinttro_handle_outbound_call',
        'permission_callback' => function ($request) {
            // Verify your WP Nonce here during staging production
            return true; 
        }
    ));
});


/**
 * Listen for public Twilio webhooks before standard routing kicks in
 */
add_action('init', function () {
    if (isset($_GET['vinttro_action']) && $_GET['vinttro_action'] === 'outbound-twiml') {
        
        // Grab tracking IDs straight from the global $_GET array
        $lead_id  = sanitize_text_field($_GET['lead_id'] ?? 'unknown_lead');
        $agent_id = sanitize_text_field($_GET['agent_id'] ?? 'vinttro-hq');

        // Force raw XML output headers
        header("Content-Type: text/xml; charset=utf-8");
        
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Response>';
        echo '    <Dial record="record-from-answer-dual">';
        echo '        <Client>' . htmlspecialchars($agent_id) . '</Client>';
        echo '    </Dial>';
        echo '</Response>';
        
        exit; // Kill execution immediately so WP layout engine doesn't bleed into the XML
    }
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
 * The logic that runs when the CRM Member API is hit
 */
function vinttro_handle_crm_member($request) {
    $params = $request->get_json_params();
    $email = sanitize_email($params['email'] ?? '');
    
    if (empty($email)) {
        return new \WP_Error('missing_email', 'Email is required', ['status' => 400]);
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
            return new \WP_Error('create_failed', $user_id->get_error_message(), ['status' => 500]);
        }
    }

    // --- Dynamic Metadata Syncing ---
    $simple_meta_keys = ['mot_date', 'insurance_renewal', 'membership_status'];
    foreach ($simple_meta_keys as $key) {
        if (isset($params[$key])) {
            update_user_meta($user_id, 'vinttro_' . $key, sanitize_text_field($params[$key]));
        }
    }

    // --- Dynamic Array Syncing (Garage & Fleets) ---
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

    return new \WP_REST_Response(['status' => 'success', 'user_id' => $user_id], 200);
}

/**
 * Handle triggering the Outbound Call from the client UI
 */
function vinttro_handle_outbound_call($request) {
    $params = $request->get_json_params();
    
    // Safety fallback defaults to prevent PHP notices if keys are missing
    $to_number = sanitize_text_field($params['toNumber'] ?? '');
    $lead_id   = sanitize_text_field($params['leadId'] ?? '');
    $agent_id  = sanitize_text_field($params['agentId'] ?? '');

    if (empty($to_number)) {
        return new \WP_REST_Response(array('success' => false, 'error' => 'Destination phone number is required.'), 400);
    }

    try {
        // 🚀 SMART FIX: Call your existing SDK helper function!
        // This handles loading the vendor/autoload.php file and log-in seamlessly via API Keys.
        $twilio = vinttro_get_twilio_sdk_client();

        // Create the outbound call channel
        $call = $twilio->calls->create(
            $to_number, 
            "+447427814474", // Put your Twilio or verified out number here
            array(
                "url" => "https://uat.vinttro.co.uk/wp-json/vinttro/v1/outbound-twiml?lead_id=" . urlencode($lead_id) . "&agent_id=" . urlencode($agent_id),
                "record" => true 
            )
        );

        return new \WP_REST_Response(array('success' => true, 'callSid' => $call->sid), 200);
    } catch (\Exception $e) {
        return new \WP_REST_Response(array('success' => false, 'error' => $e->getMessage()), 500);
    }
}
/**
 * Generates the TwiML instructions when a phone answers an ad-hoc outbound call
 */
function vinttro_handle_outbound_twiml($request) {
    // Grab our tracking IDs passed via the query string
    $lead_id  = sanitize_text_field($request->get_param('lead_id') ?? 'unknown_lead');
    $agent_id = sanitize_text_field($request->get_param('agent_id') ?? 'vinttro-hq');

    // We force PHP to spit out raw XML instead of WordPress JSON
    header("Content-Type: text/xml; charset=utf-8");
    
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response>';
    // 1. Tell Twilio to record the conversation
    echo '    <Dial record="record-from-answer-dual">';
    // 2. Dial the browser SDK client name (Make sure this matches the identity your browser token uses!)
    echo '        <Client>' . htmlspecialchars($agent_id) . '</Client>';
    echo '    </Dial>';
    echo '</Response>';
    
    exit; // Stop WordPress from executing anything further and ruining the XML format
}