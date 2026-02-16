<?php

// ---- CONFIGURATION ----

// Load the environment variables
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$suitecrm_url = $_ENV['SUITECRM_URL'];
$username = $_ENV['USERNAME'];
$password = $_ENV['PASSWORD'];
$client_id = $_ENV['CLIENT_ID'];
$client_secret = $_ENV['CLIENT_SECRET'];

$csv_file_path = '/tmp/mailchimp_contacts_1.csv';
$module_name = 'Contacts'; // Or 'Leads'

// ---- AUTHENTICATION ----
function getToken($url, $username, $password) {
    $ch = curl_init();
    $login_data = json_encode([
        'grant_type' => 'password',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'username' => $username,
        'password' => $password,
    ]);

    curl_setopt_array($ch, [
        CURLOPT_URL => str_replace('/V8/module', '/access_token', $url),
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
        die("Failed to get access token: " . $response);
    }
}

// ---- MAIN SCRIPT ----
// Get the access token
$access_token = getToken($suitecrm_url, $username, $password);
echo "Successfully authenticated. Starting import...\n";

// Open the CSV file
if (($handle = fopen($csv_file_path, "r")) === FALSE) {
    die("Failed to open CSV file.");
}

// Get the header row
$header = fgetcsv($handle);

$row_count = 0;
while (($row = fgetcsv($handle)) !== FALSE) {
    $row_count++;
    // Create an associative array from the header and row data
    $record = array_combine($header, $row);

    // Prepare the data for the API request
    $api_data = [
        'data' => [
            'type' => $module_name,
            'attributes' => [
                'first_name' => $record['First Name'],
                'last_name' => $record['Last Name']
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $suitecrm_url,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($api_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/vnd.api+json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the result
    if ($http_code == 201) {
        echo "✅ Record #$row_count imported successfully.\n";
    } else {
        echo "❌ Error importing record #$row_count. HTTP Code: $http_code. Response: $response\n";
    }
}

fclose($handle);