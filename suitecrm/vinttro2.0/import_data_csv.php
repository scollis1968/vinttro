<?php

// ---- CONFIGURATION & VALIDATION ----
if ($argc < 4) {
    die("❌ Usage: php import_data_csv.php <source_system> <data_type> <csv_file_path> [header_marker]\n");
}

$source_system = $argv[1];
$data_type     = $argv[2];
$csv_file_path = $argv[3];
// Capture the optional 4th argument (null if omitted)
$header_marker = isset($argv[4]) ? trim($argv[4]) : null;

$module_name   = 'Contacts';

include('suitecrm-common-functions.php');

// ---- MAIN SCRIPT ----
$access_token = getToken($suitecrm_url, $username, $password, $client_id, $client_secret);
echo "Successfully authenticated. Starting import...\n";

if (($handle = fopen($csv_file_path, "r")) === FALSE) {
    die("❌ Failed to open CSV file.");
}

$header = null;

// ---- DYNAMIC HEADER RESOLUTION ----
if (!empty($header_marker)) {
    // Scenario A: A marker was provided. Scan forward to find it.
    $skipped_lines = 0;
    while (($row = fgetcsv($handle)) !== FALSE) {
        $clean_row = array_map('trim', $row);
        if (in_array($header_marker, $clean_row)) {
            $header = $clean_row;
            echo "🎯 Custom marker '{$header_marker}' found. Skipped {$skipped_lines} lines.\n";
            break;
        }
        $skipped_lines++;
    }
} else {
    // Scenario B: No marker provided. Assume row 1 contains the headers.
    $first_row = fgetcsv($handle);
    if ($first_row !== FALSE) {
        $header = array_map('trim', $first_row);
        echo "📄 No header marker provided. Assuming row 1 contains headers.\n";
    }
}

// Safety fallback if something went wrong
if (!$header) {
    fclose($handle);
    die("❌ CRITICAL ERROR: Unable to resolve header columns. Check file structure.\n");
}

// ---- DATA PROCESSING LOOP ----
$row_count = 0;
while (($row = fgetcsv($handle)) !== FALSE) {
    // Skip entirely empty lines
    if (empty($row) || $row === [null] || trim(implode('', $row)) === '') {
        continue;
    }

    $row_count++;
    
    // Normalize jagged rows
    if (count($header) !== count($row)) {
        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), '');
        } else {
            $row = array_slice($row, 0, count($header));
        }
    }

    // Combine headers with data rows
    $record = array_combine($header, $row);

    // Ship to the staging engine
    $r = insertRawData($suitecrm_url, $access_token, $source_system, $data_type, $record);
    
    if (empty($r) || isset($r['errors'])) {
        echo "❌ Error importing record #$row_count.\n";
        continue;
    }
}

fclose($handle);
echo "🏁 Done! Staged $row_count data records for processing.\n";