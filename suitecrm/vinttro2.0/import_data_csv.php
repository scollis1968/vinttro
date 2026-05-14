<?php

// ---- CONFIGURATION ----

//$csv_file_path = '/tmp/mailchimp_contacts_1.csv';
$source_system = $argv[1];
$csv_file_path = $argv[2];
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
    



    $r = insertRawData($suitecrm_url,$access_token,$source_system,$record);
    // Log the result
    if (empty($r) || isset($r['errors'])) {
        echo "❌ Error importing record #$row_count.\n";
        echo "Response Details: " . json_encode($r, JSON_PRETTY_PRINT) . "\n";
        continue;
    }
 
    echo "✅ Record #$row_count imported successfully.\n";
 
}

fclose($handle);