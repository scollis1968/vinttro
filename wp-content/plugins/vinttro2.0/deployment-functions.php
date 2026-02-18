<?php
add_action('admin_menu', 'add_sync_button_to_menu');

function add_sync_button_to_menu() {
    add_management_page('Sync to Prod', 'Sync to Prod', 'manage_options', 'sync-to-prod', 'render_sync_page');
}

function render_sync_page() {
    // Path to your log file - ensure the web server has write access here!
    $log_file = WP_CONTENT_DIR . '/sync_history.log';

    if (isset($_POST['trigger_sync'])) {
        $current_user = wp_get_current_user();
        $username = $current_user->user_login;
        $timestamp = date('Y-m-d H:i:s');
        
        // 1. Prepare the log entry
        $log_entry = "[$timestamp] USER: $username initiated a Production Sync.\n";

        // 2. Write to the log file (FILE_APPEND ensures we don't overwrite previous logs)
        file_put_contents($log_file, $log_entry, FILE_APPEND);

        // 3. Call the Webhook on the Prod Server
        $response = wp_remote_post('https://your-prod-server:9000/hooks/sync-uat-content', array(
            'headers' => array(
                'X-Sync-Token' => 'YOUR_SUPER_SECRET_KEY_HERE',
                'Content-Type' => 'application/json'
            ),
            'timeout' => 5 // We just need to trigger it, not wait for the whole sync
        ));

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            file_put_contents($log_file, "[$timestamp] ERROR: Webhook failed - $error_msg\n", FILE_APPEND);
            echo '<div class="error"><p>Sync Failed: ' . esc_html($error_msg) . '</p></div>';
        } else {
            echo '<div class="updated"><p>Sync Signal Sent! The log has been updated.</p></div>';
        }
    }

    // Render the UI
    ?>
    <div class="wrap">
        <h1>Promote UAT Content to Production</h1>
        <p>This will trigger the <strong>v-sync.sh</strong> script on the production server via the webhook service.</p>
        
        <div class="card" style="max-width: 400px; padding: 20px;">
            <form method="post">
                <?php wp_nonce_field('trigger_sync_action', 'sync_nonce'); ?>
                <p><strong>Last few logs:</strong></p>
                <pre style="background: #f0f0f0; padding: 10px; font-size: 11px; max-height: 150px; overflow-y: scroll;">
<?php 
    if (file_exists($log_file)) {
        echo esc_html(implode("", array_slice(file($log_file), -5))); 
    } else {
        echo "No logs yet.";
    }
?>
                </pre>
                <input type="submit" name="trigger_sync" class="button button-primary button-large" value="Sync UAT to Prod Now" onclick="return confirm('Are you sure? This will overwrite Production WordPress data with UAT data.');">
            </form>
        </div>
    </div>
    <?php
}