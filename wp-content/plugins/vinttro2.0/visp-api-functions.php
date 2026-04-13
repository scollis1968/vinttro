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
/ --- Handling the Fleet data ---
if (isset($params['fleets']) && is_array($params['fleets'])) {
    $fleet_data = [];

    foreach ($params['fleets'] as $fleet) {
        $fleet_vehicles = []; 

        if (isset($fleet['vehicles']) && is_array($fleet['vehicles'])) {
            foreach ($fleet['vehicles'] as $vehicle) {
                
                // --- Handle Outstanding Issues for this specific vehicle ---
                $issues = [];
                if (!empty($vehicle['outstanding_issues']) && is_array($vehicle['outstanding_issues'])) {
                    foreach ($vehicle['outstanding_issues'] as $issue) {
                        $issues[] = [
                            'title'         => sanitize_text_field($issue['title'] ?? ''),
                            'description'   => sanitize_textarea_field($issue['description'] ?? ''),
                            'severity'      => sanitize_text_field($issue['issue_severity'] ?? 'low'),
                            'date_reported' => sanitize_text_field($issue['date_issue_reported'] ?? ''),
                        ];
                    }
                }

                $fleet_vehicles[] = [
                    'fleet'             => sanitize_text_field($vehicle['fleet'] ?? ''), 
                    'make'              => sanitize_text_field($vehicle['make'] ?? ''),
                    'model'             => sanitize_text_field($vehicle['model'] ?? ''),
                    'reg'               => sanitize_text_field($vehicle['reg'] ?? ''),
                    'mot_expiry'        => sanitize_text_field($vehicle['mot_expiry'] ?? ''),
                    'date_next_service' => sanitize_text_field($vehicle['date_next_service'] ?? ''),
                    'date_last_check'   => sanitize_text_field($vehicle['date_last_check'] ?? ''),
                    'ins_expiry'        => sanitize_text_field($vehicle['ins_expiry'] ?? ''),
                    'image_url'         => esc_url_raw($vehicle['image_url'] ?? ''),
                    'driver_name'       => sanitize_text_field($vehicle['driver_name'] ?? ''),
                    'outstanding_issues' => $issues // Save the array of issues
                ];
            }
        }

        $fleet_data[] = [
            'name'     => sanitize_text_field($fleet['name'] ?? ''),
            'vehicles' => $fleet_vehicles 
        ];
    }
    update_user_meta($user_id, 'vinttro_fleets', $fleet_data);
}