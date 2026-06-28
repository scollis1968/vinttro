<?php
// 1. Establish secure entry point and boot the SuiteCRM core engine
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';
require_once 'custom/include/DataStaging/Mappers/WordPressVehicleCheckMapper.php';

// Force errors to print directly out to your terminal screen instead of hiding them
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

global $current_user;
if (empty($current_user)) {
    $current_user = BeanFactory::newBean('Users');
    $current_user->getSystemUser(); // Act as system admin bypass ACL limits
}

echo "=== VINTTRO ISOLATED MAPPER TEST START ===\n";

// 2. Locate your specific WordPress pending record
$stagingBean = BeanFactory::newBean('visp_data_staging');
$pendingRecords = $stagingBean->get_full_list(
    '', 
    "visp_data_staging.status = 'pending' AND visp_data_staging.source_system = 'WordPress'", 
    false, 
    1
);

if (empty($pendingRecords)) {
    echo "❌ ERROR: No pending WordPress records found in visp_data_staging table.\n";
    echo "Go to the CRM UI, find your test record, and ensure its core status is manually flipped back to 'pending'.\n";
    exit;
}

$record = reset($pendingRecords);
echo "🟢 FOUND RECORD ID: " . $record->id . "\n";
echo "🟢 NAME: " . $record->name . "\n";

// 3. Extract and decode the raw JSON payload data 
$rawDataString = html_entity_decode($record->raw_data, ENT_QUOTES, 'UTF-8');
$decodedPayload = json_decode($rawDataString, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ ERROR: Failed to decode raw_data JSON string: " . json_last_error_msg() . "\n";
    exit;
}

// 4. Instantiate and execute your mapper directly
try {
    echo "🚀 Initializing WordPressVehicleCheckMapper...\n";
    $mapper = new \Custom\DataStaging\Mappers\WordPressVehicleCheckMapper();
    
    echo "⚙️  Executing process() loop...\n";
    $resultMessage = $mapper->process($decodedPayload, $record);
    
    echo "✅ SUCCESS OUTCOME: " . $resultMessage . "\n";
    
    // Explicitly update the status here to verify tracking
    $record->status = 'processed';
    $record->save();
    echo "💾 Staging record status successfully updated to 'processed'.\n";

} catch (\TypeError $te) {
    echo "💥 CRITICAL PHP TYPE ERROR CAUGHT:\n";
    echo "Message: " . $te->getMessage() . "\n";
    echo "File:    " . $te->getFile() . "\n";
    echo "Line:    " . $te->getLine() . "\n";
    echo "Stack Trace:\n" . $te->getTraceAsString() . "\n";

} catch (\Exception $e) {
    echo "💥 RUNTIME EXCEPTION CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File:    " . $e->getFile() . "\n";
    echo "Line:    " . $e->getLine() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "=== VINTTRO ISOLATED MAPPER TEST END ===\n";