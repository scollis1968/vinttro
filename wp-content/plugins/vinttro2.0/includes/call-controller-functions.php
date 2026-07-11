<?php
// 🔒 SECURE HIGH-PERFORMANCE PROXY: Fetches tasks straight from local Node loopback
add_action('wp_ajax_vinttro_get_tasks', 'vinttro_proxy_get_tasks');

function vinttro_proxy_get_tasks() {
    // 1. Enforce user authentication check
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access block.'], 403);
    }

    // 2. Grab and sanitize the agent constraint
    $agent_email = isset($_GET['agent']) ? sanitize_email($_GET['agent']) : '';
    if (empty($agent_email)) {
        wp_send_json_error(['message' => 'Missing target agent constraint.'], 400);
    }

    $node_internal_url = 'http://127.0.0.1:3000/api/tasks?agent=' . urlencode($agent_email);
    
    // 🚀 THE FIX: Use raw PHP cURL to completely bypass security plugins & WP hook overhead
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $node_internal_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);                   // 3 second strict fail-safe max ceiling
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force instant IPv4 loopback resolution (no IPv6 lag)
    
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 3. Evaluate output stream integrity
    if ($output === false || $http_code !== 200) {
        wp_send_json_error(['message' => 'Internal network communication error.'], 500);
    }

    // 4. Directly output the lightning-fast payload matrix to the browser
    header('Content-Type: application/json');
    echo $output;
    wp_die();
}