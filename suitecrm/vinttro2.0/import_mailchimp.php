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
    
    // 2.5 Robust Phone Mapping (Mobile vs Work)
    $raw_phone = trim($record['Phone Number']);

    if (!empty($raw_phone)) {
        // Pattern logic: 
        // ^      = Start of the string
        // (07|7|\\+447) = Matches '07' OR '7' OR '+447'
        // Note: UK mobiles always have a '7' after the country code
        if (preg_match('/^(07|7|\+447)/', $raw_phone)) {
            $contact['phone_mobile'] = $raw_phone;
            echo "📱 Mapping $raw_phone to Mobile\n";
        } else {
            $contact['phone_work'] = $raw_phone;
            echo "📞 Mapping $raw_phone to Work Phone\n";
        }
    }

    $r = insertUpdateContact($suitecrm_url,$access_token,$contact);

    // Log the result
    if (empty($r)) {
        echo "❌ Error importing record #$row_count.";
        continue;
    }

    echo "✅ Record #$row_count imported successfully.\n";
 
}

fclose($handle);