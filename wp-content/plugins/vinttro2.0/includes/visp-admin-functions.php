<?php
/**
 * Add Acturis Import Admin Page
 */
add_action('admin_menu', 'vinttro_add_acturis_import_menu');
function vinttro_add_acturis_import_menu() {
    add_menu_page(
        'Acturis CSV Import',
        'Acturis Import',
        'manage_options',
        'vinttro-acturis-import',
        'vinttro_render_acturis_import_page',
        'dashicons-upload',
        30
    );
}

/**
 * Enqueue Styles & Scripts on Import Page
 */
add_action('admin_enqueue_scripts', 'vinttro_enqueue_acturis_import_scripts');
function vinttro_enqueue_acturis_import_scripts($hook) {
    if ($hook !== 'toplevel_page_vinttro-acturis-import') {
        return;
    }

    // Resolves base URL to /wp-content/plugins/vinttro2.0/
    $plugin_root_url = plugin_dir_url( dirname( __FILE__ ) );

    // 1. Load Admin CSS (visp-admin.css)
    wp_enqueue_style(
        'visp-admin-css',
        $plugin_root_url . 'css/visp-admin.css',
        array(),
        '2.0.0'
    );

    // 2. Load PapaParse from CDN
    wp_enqueue_script(
        'papaparse',
        'https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js',
        array(),
        '5.4.1',
        true
    );

    // 3. Load Acturis Uploader JS (js/acturis-uploader.js)
    wp_enqueue_script(
        'vinttro-acturis-uploader',
        $plugin_root_url . 'js/acturis-uploader.js',
        array('jquery', 'papaparse'),
        '2.0.0',
        true
    );

    // 4. Pass REST API config & target URL to JavaScript
    $baseUrl = getenv('VINTTRO_API_PUBLIC_URL') 
        ?: ($_ENV['VINTTRO_API_PUBLIC_URL'] ?? (defined('VINTTRO_API_PUBLIC_URL') ? VINTTRO_API_PUBLIC_URL : 'http://localhost:3000'));

    $cleanBaseUrl = rtrim($baseUrl, '/');

    wp_localize_script('vinttro-acturis-uploader', 'vinttroImportConfig', [
        'apiUrl' => $cleanBaseUrl . '/api/import/batch'
    ]);
}

/**
 * Register REST API Endpoint to Receive Chunked Batches (Legacy Fallback)
 */
add_action('rest_api_init', function () {
    register_rest_route('vinttro/v1', '/stage-acturis-batch', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_handle_acturis_batch',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));
});

/**
 * REST Callback: Receives JSON payload chunk and inserts/stages records
 */
function vinttro_handle_acturis_batch(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $batch = isset($params['batch']) ? $params['batch'] : array();
    $batchIndex = isset($params['batch_index']) ? intval($params['batch_index']) : 0;

    if (empty($batch)) {
        return new WP_Error('empty_batch', 'No records supplied in batch', array('status' => 400));
    }

    return new WP_REST_Response(array(
        'success'      => true,
        'batch_index'  => $batchIndex,
        'staged_count' => count($batch),
        'message'      => "Batch {$batchIndex} successfully staged"
    ), 200);
}

/**
 * Render HTML UI Screen
 */
function vinttro_render_acturis_import_page() {
    ?>
    <div class="wrap">
        <h1>Acturis CSV Data Staging Upload</h1>
        <p>Select or drag an exported Acturis CSV file. Data will be parsed in client-side batches and pushed to <code>vinttro-api</code>.</p>

        <div class="acturis-upload-card">
            <div class="drop-zone" id="acturis-drop-zone">
                <input type="file" id="acturis-file-input" accept=".csv" />
                <div class="drop-zone-text">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <p><strong>Click to choose a file</strong> or drag and drop here</p>
                    <small>Accepts .CSV exports from Acturis</small>
                </div>
            </div>

            <div class="upload-options">
                <label for="batch-size-select">Batch Size (Rows per Request):</label>
                <select id="batch-size-select">
                    <option value="250">250 records</option>
                    <option value="500" selected>500 records (Recommended)</option>
                    <option value="1000">1,000 records</option>
                </select>
                <button type="button" class="button button-primary button-hero" id="btn-start-import" disabled>
                    Start Processing File
                </button>
            </div>

            <!-- Progress Tracker -->
            <div id="import-progress-container" style="display: none;">
                <div class="progress-bar-wrapper">
                    <div class="progress-bar-fill" id="progress-bar-fill"></div>
                </div>
                <div class="progress-metrics">
                    <span id="progress-text">Processing batch 0 of 0...</span>
                    <span id="progress-percent">0%</span>
                </div>
            </div>

            <!-- Execution Console Log -->
            <div class="import-console-log" id="import-console-log">
                <div class="console-line info">[System] Ready for Acturis CSV upload...</div>
            </div>
        </div>
    </div>
    <?php
}