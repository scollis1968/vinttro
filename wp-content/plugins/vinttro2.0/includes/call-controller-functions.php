<?php
// 🚀 HIGH-PERFORMANCE REST PIPELINE: Registers a ultra-fast, lightweight proxy route
add_action('rest_api_init', function () {
    register_rest_route('vinttro/v1', '/tasks', [
        'methods'             => 'GET',
        'callback'            => 'vinttro_secure_rest_tasks',
        'permission_callback' => function () {
            // Hardened security boundary: Users must be logged into WP to view tasks
            return is_user_logged_in();
        }
    ]);
});

function vinttro_secure_rest_tasks($request) {
    // 1. Grab parameters directly from the clean REST request matrix
    $agent_email = $request->get_param('agent');
    if (empty($agent_email)) {
        return new WP_Error('missing_param', 'Missing target agent constraint.', ['status' => 400]);
    }

    $node_internal_url = 'http://127.0.0.1:3000/api/tasks?agent=' . urlencode($agent_email);

    // 2. Direct sub-millisecond local loopback handshake via raw cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $node_internal_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);                   // Strict 2-second timeout window
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Avoid IPv6 resolution delays
    
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($output === false || $http_code !== 200) {
        return new WP_Error('node_offline', 'Internal network communication error.', ['status' => 500]);
    }

    // 3. Decode payload and stream it back instantly via native fast JSON classes
    $data = json_decode($output, true);
    return new WP_REST_Response($data, 200);
}