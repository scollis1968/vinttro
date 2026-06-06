<?php
add_action('admin_menu', 'add_sync_button_to_menu');

function add_sync_button_to_menu() {
    add_management_page('Production Deployment', 'Sync to Prod', 'manage_options', 'sync-to-prod', 'render_sync_page');
}

function render_sync_page() {
    $log_file = WP_CONTENT_DIR . '/sync_history.log';
    $timestamp = date('Y-m-d H:i:s');
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;

    // --- PROCESS THE SYNC ACTIONS ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Security check: Verify the request came from this page
        if (!isset($_POST['sync_nonce']) || !wp_verify_nonce($_POST['sync_nonce'], 'trigger_sync_action')) {
            echo '<div class="error"><p>Security check failed. Please refresh and try again.</p></div>';
            return;
        }

        $endpoint = '';
        $action_type = '';

        if (isset($_POST['trigger_wp_sync'])) {
            $action_type = "WordPress Content Sync";
            $endpoint = 'https://services.prod.vinttro.co.uk/hooks/sync-uat-content';
        } elseif (isset($_POST['trigger_crm_sync'])) {
            $action_type = "SuiteCRM System Sync";
            $endpoint = 'https://services.prod.vinttro.co.uk/hooks/sync-uat-crm'; // New endpoint
        }

        if (!empty($endpoint)) {
            // 1. Log the initiation
            $log_entry = "[$timestamp] USER: $username initiated a [$action_type].\n";
            file_put_contents($log_file, $log_entry, FILE_APPEND);

            // 2. Fire the Webhook to Prod
            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'X-Sync-Token' => '63f4945d921d599f27ae4fdf5bada3f2',
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 5
            ));

            // 3. Handle feedback
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                file_put_contents($log_file, "[$timestamp] ERROR: Webhook failed for $action_type - $error_msg\n", FILE_APPEND);
                echo '<div class="error"><p>' . esc_html($action_type) . ' Failed: ' . esc_html($error_msg) . '</p></div>';
            } else {
                echo '<div class="updated"><p>🎯 ' . esc_html($action_type) . ' signal sent successfully! Check logs on production for real-time tracking.</p></div>';
            }
        }
    }

    // --- RENDER UI ---
    ?>
    <div class="wrap">
        <h1>Promote UAT Environments to Production</h1>
        <p>Select the specific platform you wish to sync. Moving these separately keeps incomplete work safe from bleeding into Production.</p>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
            
            <div class="card" style="flex: 1; min-width: 300px; max-width: 450px; padding: 20px; border-top: 4px solid #0073aa;">
                <h2>🌐 WordPress Sync</h2>
                <p>Syncs uploads, active plugins, child themes, and completely overwrites the Production database with UAT entries.</p>
                <div style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 10px; margin-bottom: 15px;">
                    <strong>⚠️ Warning:</strong> Live production user data or posts added directly to Prod will be overwritten.
                </div>
                <form method="post">
                    <?php wp_nonce_field('trigger_sync_action', 'sync_nonce'); ?>
                    <input type="submit" name="trigger_wp_sync" class="button button-primary button-large" value="Sync WordPress Now" 
                           onclick="return confirm('Are you absolutely sure? This completely overwrites your live WordPress production database.');">
                </form>
            </div>

            <div class="card" style="flex: 1; min-width: 300px; max-width: 450px; padding: 20px; border-top: 4px solid #f05a28;">
                <h2>🏢 SuiteCRM Sync</h2>
                <p>Syncs your front-end layout extensions, backend event hooks, and files for the <strong>"visp"</strong> custom module.</p>
                <div style="background: #f0fbfc; border-left: 4px solid #00a0d2; padding: 10px; margin-bottom: 15px;">
                    <strong>ℹ️ Automation Notice:</strong> This triggers an automated metadata extension rebuild and handles DB schema migrations safely without erasing customer CRM records.
                </div>
                <form method="post">
                    <?php wp_nonce_field('trigger_sync_action', 'sync_nonce'); ?>
                    <input type="submit" name="trigger_crm_sync" class="button style" style="background: #f05a28; color: white; border-color: #d44617;" value="Sync SuiteCRM Now" 
                           onclick="return confirm('Are you sure you want to promote the SuiteCRM code layout to production? This will clear CRM app caches.');">
                </form>
            </div>
            
        </div>

        <div class="card" style="margin-top: 25px; max-width: 920px;">
            <h3>📋 Recent Deployment History</h3>
            <pre style="background: #f0f0f0; padding: 15px; font-size: 12px; font-family: monospace; max-height: 200px; overflow-y: scroll; border: 1px solid #ccc;">
<?php 
if (file_exists($log_file)) {
    $lines = file($log_file);
    echo esc_html(implode("", array_slice($lines, -8))); 
} else {
    echo "No history logged yet.";
}
?>
            </pre>
        </div>
    </div>
    <?php
}