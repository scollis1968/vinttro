<?php
// PHP Script for SuiteCRM V8 Contact Upsert (Insert/Update)
// This script assumes it is run from the /var/www/suitecrm8.8/ directory 
// and that the vendor/autoload.php is present in the root.

// ---------------------------------------------------------------------
// CONFIGURATION AND AUTOLOAD
// ---------------------------------------------------------------------

// Require the Composer autoloader from the SuiteCRM root
// Adjust path to point to the correct vendor directory
require __DIR__ . '/vendor/autoload.php';

// Load environment variables from the .env file in the current directory
// Using the legacy syntax compatible with older SuiteCRM versions of phpdotenv
try {
    $dotenv = new Dotenv\Dotenv(__DIR__); 
    $dotenv->load();
} catch (\Exception $e) {
    // Gracefully handle the case where .env file loading fails
    die("Error loading .env file: " . $e->getMessage());
}

// Global Variables loaded from .env
// We retrieve them outside the functions for the main script use
$suitecrm_url    = $_ENV['SUITECRM_URL'] ?? null;
$username        = $_ENV['USERNAME'] ?? null;
$password        = $_ENV['PASSWORD'] ?? null;
$client_id       = $_ENV['CLIENT_ID'] ?? null;
$client_secret   = $_ENV['CLIENT_SECRET'] ?? null;

// Validate that required environment variables are set
if (empty($suitecrm_url) || empty($username) || empty($password) || empty($client_id) || empty($client_secret)) {
    die("FATAL ERROR: One or more required environment variables (SUITECRM_URL, USERNAME, PASSWORD, CLIENT_ID, CLIENT_SECRET) are missing or empty in the .env file.");
}


// ---------------------------------------------------------------------
// API HELPER FUNCTIONS
// ---------------------------------------------------------------------

/**
 * Retrieves an access token from the SuiteCRM API.
 * * @param string $url The base URL for the SuiteCRM API (e.g., http://crm/V8/module).
 * @param string $username The API username.
 * @param string $password The API password.
 * @param string $client_id The OAuth 2.0 Client ID.
 * @param string $client_secret The OAuth 2.0 Client Secret.
 * @return string|null The access token or null on failure.
 */
function getToken($url, $username, $password, $client_id, $client_secret) {
    $ch = curl_init();
    
    // Construct the token endpoint URL
    $token_url = str_replace('/V8/module', '/access_token', $url);

    $login_data = json_encode([
        'grant_type' => 'password',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'username' => $username,
        'password' => $password,
    ]);

    curl_setopt_array($ch, [
        CURLOPT_URL => $token_url,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $login_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/vnd.api+json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        // Use error_log instead of die() inside a function for better debugging flow
        error_log("Failed to get access token: " . $response);
        return null;
    }
}

/**
 * Executes a cURL request to the SuiteCRM API.
 * * @param string $url The full API endpoint URL.
 * @param string $method The HTTP method (POST, PATCH, GET).
 * @param string $access_token The valid access token.
 * @param array $api_data Optional data payload for POST/PATCH.
 * @return array The decoded JSON response data.
 */
function callApi($url, $method, $access_token, $api_data = []) {
    $ch = curl_init();
    
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/vnd.api+json'
    ];
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];

    if ($method === 'POST' || $method === 'PATCH') {
        $options[CURLOPT_POSTFIELDS] = json_encode($api_data);
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    
    // Check for common API errors
    if ($http_code >= 400) {
        error_log("API Error ($http_code) for $method $url: " . ($response ?: 'No response body.'));
        return ['error' => true, 'http_code' => $http_code, 'response' => $data];
    }
    
    return $data;
}


// ---------------------------------------------------------------------
// CORE CONTACT UPSERT FUNCTION
// ---------------------------------------------------------------------

/**
 * Searches for a Contact by email address.
 * * @param string $api_url Base SuiteCRM API URL.
 * @param string $access_token Access token.
 * @param string $email The email address to search for.
 * @return string|null The Contact ID if found, otherwise null.
 */
function findContactByEmail($api_url, $access_token, $email) {
    // SuiteCRM V8 uses 'filter' endpoint for complex searches
    $filter_url = $api_url . '/Contacts/filter';

    $filter_data = [
        'filter' => [
            'emails.email_address' => [
                '$equals' => $email
            ]
        ],
        'fields' => 'id',
        'max_num' => 1
    ];

    $response = callApi($filter_url, 'POST', $access_token, $filter_data);

    if (isset($response['data'][0]['id'])) {
        return $response['data'][0]['id']; // Return the ID of the existing contact
    }
    
    return null; // Contact not found
}

/**
 * Inserts or Updates a Contact based on email address.
 * * @param string $api_url The base API URL.
 * @param string $access_token The valid access token.
 * @param array $contact_data The contact fields (must include 'email1').
 */
function upsertContact($api_url, $access_token, $contact_data) {
    
    if (empty($contact_data['email1'])) {
        error_log("ERROR: 'email1' is required for upsert operation.");
        return;
    }
    
    $email = $contact_data['email1'];
    $contact_id = findContactByEmail($api_url, $access_token, $email);
    
    $method = 'POST';
    $url = $api_url . '/Contacts';
    $message = "Created new Contact";
    $status_code = 201; // Expected HTTP status for creation

    // 1. Prepare the JSON API payload structure
    $payload = [
        'data' => [
            'type' => 'Contacts',
            'attributes' => [
                'first_name' => $contact_data['first_name'] ?? null,
                'last_name' => $contact_data['last_name'] ?? null,
                // Map the email to the primary email field
                'email1' => $email, 
                // Add any other fields you need here (e.g., 'phone_work', 'title')
            ],
            // Include relationships if needed (e.g., relating to an Account)
            // 'relationships' => [...]
        ]
    ];

    if ($contact_id) {
        // 2. Contact exists: Switch to PATCH (Update)
        $method = 'PATCH';
        $url .= '/' . $contact_id;
        $message = "Updated existing Contact (ID: $contact_id)";
        $status_code = 200; // Expected HTTP status for update
        
        // Add the ID to the payload data for the PATCH request
        $payload['data']['id'] = $contact_id;
    }

    echo "Attempting to $method contact for email: $email...\n";

    // 3. Execute API call
    $response = callApi($url, $method, $access_token, $payload);
    
    // 4. Log the result
    if (!isset($response['error']) && (isset($response['meta']['status']) && $response['meta']['status'] == $status_code) || (isset($response['data']['id']))) {
        echo "✅ SUCCESS: $message.\n";
    } else {
        echo "❌ FAILURE: Failed to process contact.\n";
        print_r($response);
    }
}


// ---------------------------------------------------------------------
// MAIN EXECUTION
// ---------------------------------------------------------------------

// 1. Get Access Token
$access_token = getToken($suitecrm_url, $username, $password, $client_id, $client_secret);

if (!$access_token) {
    die("Script execution aborted due to failed API authentication.");
}

// 2. Define Contact Data to be inserted/updated
$new_contact = [
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'email1' => 'jane.smith@example.com', // This is the unique identifier for upsert
    'title' => 'Director of QA',
    'phone_work' => '555-123-4567',
];

// 3. Run the Upsert Operation
upsertContact($suitecrm_url, $access_token, $new_contact);

// Example 2: Run an update on the same record (if it exists)
$updated_contact = [
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'email1' => 'jane.smith@example.com', 
    'title' => 'Chief Technology Officer', // Field change here
];

upsertContact($suitecrm_url, $access_token, $updated_contact);

?>
