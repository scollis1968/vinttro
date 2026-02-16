<?php
// ---- CONFIGURATION ----
// Load the environment variables
//TODO - sort out the location for composer?

require __DIR__ . '/../vendor/autoload.php';
// Use the createImmutable() method which is required for phpdotenv v5.0+
//$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ... rest of your code

$suitecrm_url = $_ENV['SUITECRM_URL'];
$username = $_ENV['USERNAME'];
$password = $_ENV['PASSWORD'];
$client_id = $_ENV['CLIENT_ID'];
$client_secret = $_ENV['CLIENT_SECRET'];


// ---- AUTHENTICATION ----
function getToken($url, $username, $password, $client_id, $client_secret ) {
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

function postData($url, $access_token, $api_data, $row_count){
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
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
        return $response;
    } else {
        echo "❌ Error importing record #$row_count. HTTP Code: $http_code. Response: $response\n";
        return null;
    }
}
function patchData($url, $access_token, $api_data, $row_count){
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
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
        return $response;
    } else {
        echo "❌ Error importing record #$row_count. HTTP Code: $http_code. Response: $response\n";
        return null;
    }
}

function callApi($url, $access_token, $method, $payload){
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => json_encode($payload),
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
    $data = json_decode($response, true);
    return $data;
}

function findContactByEmail($api_url, $access_token, $email) {
    // SuiteCRM V8 uses 'filter' endpoint for complex searches
    $filter_url = $api_url . '/Contacts?';

    $filter_url  = $filter_url . 'filter[email1][eq]=' . $email; 
    // TODO get it working looking at all email fields.
    //$filter_url  = $filter_url . '&filter[1][email2][eq]=' . $email; 

    $response = callApi($filter_url,$access_token,'GET','');

    if (isset($response['error'])) {
        error_log("Search filter API call failed.");
        return null; 
    }

    if (isset($response['data']) && is_array($response['data']) && !empty($response['data']) && isset($response['data'][0]['id'])) {
        echo "✅  findContactByEmail found $email\n";
        return $response['data'][0]; // Return the existing contact
    }
    
    
    echo "\u{2139}" . " findContactByEmail Not found $email\n";
    return null; // Contact not found
}

function insertUpdateContact($api_url,$access_token,$contact_data){
 
    // Prepare the data for the API request

    $payload = [
        'data' => [
            'type' => 'Contact',
            'attributes' => $contact_data
        ]
    ];
    $method = 'POST';

    $existingContact = findContactByEmail($api_url,$access_token,$contact_data['email1']);
    
    if ($existingContact) {
        $payload['data']['id'] = $existingContact['id'];
        $method = 'PATCH';
    };

    $r = callApi($api_url, $access_token, $method, $payload);
    
    return $r;
}
