Here is the mapped layout of your exact SuiteCRM field names from the Module Builder table, along with the fully updated integration code to match.

### Field Mapping

| Old Placeholder Name | Actual SuiteCRM Field Name | Type | Purpose |
| --- | --- | --- | --- |
| `status_c` | **`status`** | Dropdown | Records processing state (e.g., `pending`) |
| `entry_type_c` | **`data_type`** | TextField | Discriminator for the scheduler (`quote_request` / `vehicle_check`) |
| `raw_payload_data_c` | **`raw_data`** | TextArea | Holds the raw JSON form dump |
| `source_c` | **`source_system`** | Dropdown | Identifies the origin system |

> ⚠️ **Important Note on Dropdowns:** For the `status` and `source_system` fields, ensure that the values sent (`pending` and `WordPress`) exactly match the **Item Name (Key)** options configured inside your SuiteCRM Dropdown Editor, otherwise the API may reject them or leave them blank.

---

### Refactored Plugin Code

Replace the contents of your `/wp-content/plugins/vinttro2.0/includes/suitecrm-integration.php` file with this version tailored exactly to your schema:

```php
<?php
/**
 * Plugin Name: SuiteCRM Integration for CF7 (Refactored)
 * Description: Drops raw form submissions directly into visp_data_staging using exact database field mappings.
 */

add_action('wpcf7_before_send_mail', 'suitecrm_forward_to_staging');

function suitecrm_forward_to_staging($contact_form) {
    error_log("SuiteCRM Integration: Form submission hook triggered.");

    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $form_id   = $contact_form->id();
    $form_data = $submission->get_posted_data();

    // 1. Map Form IDs to data types and tracking names
    switch ($form_id) {
        case 2303:
            $entry_type = 'quote_request';
            $entry_name = 'Web Quote Request - ' . ($form_data['last-name'] ?? 'Unknown');
            break;
        
        case 1234: // REPLACE with your actual Vehicle Check Sheet CF7 Form ID
            $entry_type = 'vehicle_check';
            $entry_name = 'Vehicle Check Sheet - ' . ($form_data['vehicle-reg__1'] ?? 'Unknown Reg');
            break;

        default:
            // If the form ID isn't one we care about mapping, exit early.
            return;
    }

    // 2. Fetch Cached OAuth Access Token
    $token = suitecrm_get_access_token();
    if (!$token) {
        error_log("SuiteCRM Integration CRITICAL: Failed to retrieve API access token.");
        return;
    }

    // 3. Build payload matching exact visp_data_staging fields
    $url = rtrim(SUITECRM_URL, '/') . '/V8/module';
    $payload = [
        'data' => [
            'type' => 'visp_data_staging',
            'attributes' => [
                'name'          => $entry_name,
                'status'        => 'pending',               // Match exact key in your status dropdown
                'data_type'     => $entry_type,            // String token used by your scheduler logic
                'raw_data'      => json_encode($form_data), // The raw text area block dump
                'source_system' => 'WordPress'              // Match exact key in your source dropdown
            ]
        ]
    ];

    // 4. POST payload to SuiteCRM
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/vnd.api+json',
            'Accept'        => 'application/vnd.api+json'
        ],
        'body'    => json_encode($payload),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('SuiteCRM Integration Staging Error: ' . $response->get_error_message());
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        error_log("SuiteCRM Integration Success: Form ID {$form_id} forwarded to staging. HTTP Status: {$status_code}");
    }
}

/**
 * Dynamically fetches and caches the SuiteCRM API Token using WP Transients
 */
function suitecrm_get_access_token() {
    $cached_token = get_transient('suitecrm_api_token');
    if ($cached_token) {
        return $cached_token;
    }

    if (!defined('SUITECRM_CLIENT_ID') || !defined('SUITECRM_CLIENT_SECRET') || !defined('SUITECRM_URL') || !defined('SUITECRM_USERNAME') || !defined('SUITECRM_PASSWORD')) { 
        error_log(__FUNCTION__ . ': Missing SuiteCRM configuration constants in wp-config.php');
        return null;
    }

    $api_url = rtrim(SUITECRM_URL, '/') . '/access_token';

    $response = wp_remote_post($api_url, [
        'body' => [
            'grant_type'    => 'password',
            'client_id'     => SUITECRM_CLIENT_ID,
            'client_secret' => SUITECRM_CLIENT_SECRET,
            'username'      => SUITECRM_USERNAME,
            'password'      => SUITECRM_PASSWORD
        ],
        'timeout'   => 15,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        error_log('SuiteCRM API Auth Error: ' . $response->get_error_message());
        return null;
    }

    $body  = json_decode(wp_remote_retrieve_body($response), true);
    $token = $body['access_token'] ?? null;

    if ($token) {
        // Cache for 55 minutes (3300 seconds) to avoid immediate race-conditions on expiration
        set_transient('suitecrm_api_token', $token, 3300);
    } else {
        error_log('SuiteCRM API Auth Error: No Token returned in body ' . json_encode($body));
    }

    return $token;
}

```