<?php

// ---- CONFIGURATION ----

$csv_file_path = '/tmp/mailchimp_contacts_1.csv';
$module_name = 'Contacts';

include('suitecrm-common-functions.php');

// ---- MAIN SCRIPT ----
// Get the access token
$access_token = getToken($suitecrm_url, $username, $password,$client_id,$client_secret);
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

    $r = findContactByEmail($suitecrm_url,$access_token,$record['Email Address']);

    // Prepare the data for the API request
    $api_data = [
        'data' => [
            'type' => $module_name,
            'attributes' => [
                'first_name' => $record['First Name'],
                'last_name' => $record['Last Name'],
                'email1' => $record['Email Address'],
                'phone_work' => $record['Phone Number']
            ]
        ]
    ];
    
    $r = postData($suitecrm_url,$access_token,$api_data,$row_count);
    
    // Log the result
    if (empty($r)) {
        echo "❌ Error importing record #$row_count.";
        continue;
    }

    echo "✅ Record #$row_count imported successfully.\n";
 
}

fclose($handle);