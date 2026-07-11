<?php
// 🔒 SECURE INTERNAL PROXY: Fetch tasks from Node locally and return to browser
add_action('wp_ajax_vinttro_get_tasks', 'vinttro_proxy_get_tasks');

function vinttro_proxy_get_tasks() {
    // 1. Enforce baseline security check
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access block.'], 403);
    }

    // 2. Grab the requested agent parameter
    $agent_email = isset($_GET['agent']) ? sanitize_email($_GET['agent']) : '';
    if (empty($agent_email)) {
        wp_send_json_error(['message' => 'Missing target agent constraint.'], 400);
    }

    // 3. Query the Node.js call-controller STRICTLY over the local loopback network
    $node_internal_url = 'http://127.0.0.1:3000/api/tasks?agent=' . urlencode($agent_email);
    
    $response = wp_remote_get($node_internal_url, [
        'timeout' => 5 // Fast fail-safe timeout
    ]);

    // 4. Handle internal system offline states gracefully
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Internal network communication error.'], 500);
    }

    // 5. Unpack the payload body and stream it straight back to the browser
    $body = wp_remote_retrieve_body($response);
    
    header('Content-Type: application/json');
    echo $body;
    wp_die();
}