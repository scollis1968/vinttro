<?php

// ---- CONFIGURATION ----

//$csv_file_path = '/tmp/mailchimp_contacts_1.csv';
$csv_file_path = $argv[1];
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

    $contact = [
        'first_name' => $record['First Name'],
        'last_name' => $record['Last Name'],
        'email1' => $record['Email Address'],    
    ];
    // TODO - Code a robust import of phone number into the correct field, ie 07,7,+447 all go into mobile field.
    if ($record['Phone Number']) {$contact['phone_work'] = $record['Phone Number']; };

    $r = insertUpdateContact($suitecrm_url,$access_token,$contact);

    // Log the result
    if (empty($r)) {
        echo "❌ Error importing record #$row_count.";
        continue;
    }

    echo "✅ Record #$row_count imported successfully.\n";
 
}

fclose($handle);