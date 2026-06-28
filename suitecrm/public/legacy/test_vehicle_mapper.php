<?php
// 1. Establish secure entry point and boot the SuiteCRM core engine
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

// Load foundational custom framework dependencies sequential track
require_once 'custom/include/DataStaging/StagingMapperInterface.php';
require_once 'custom/include/DataStaging/AbstractStagingMapper.php';
require_once 'custom/include/DataStaging/Mappers/WordPressVehicleCheckMapper.php';

// Force comprehensive error reporting straight to terminal screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

global $current_user;
if (empty($current_user)) {
    $current_user = BeanFactory::newBean('Users');
    $current_user->getSystemUser(); // Elevate permissions to bypass front-end ACLs
}

echo "=== VINTTRO AUTOMATED MAPPER TEST START ===\n";

$record = null;
$stagingBean = BeanFactory::newBean('visp_data_staging');

// 2. CHECK FOR RUNTIME ARGUMENTS: e.g., "php test_vehicle_mapper.php e42a74db..."
if (isset($argv[1]) && !empty(trim($argv[1]))) {
    $targetId = trim($argv[1]);
    echo "🎯 Mode: Target testing explicit record ID argument [{$targetId}]\n";
    $record = BeanFactory::getBean('visp_data_staging', $targetId);
    
    if (!$record || empty($record->id)) {
        echo "❌ ERROR: Explicit record ID '{$targetId}' could not be found in the database.\n";
        exit(1);
    }
} else {
    // 3. DYNAMIC FALLBACK MODE: Scan dynamically for real active data
    echo "🔍 Mode: Dynamically searching for the newest pending WordPress record...\n";
    
    // Explicitly enforce non-deleted, active WordPress records
    $whereClause = "visp_data_staging.source_system LIKE 'WordPress%' 
                    AND visp_data_staging.status = 'pending' 
                    AND visp_data_staging.deleted = 0";
                    
    $records = $stagingBean->get_full_list('visp_data_staging.date_entered DESC', $whereClause, false, 1);

    if (!empty($records)) {
        $record = reset($records);
    } else {
        echo "⚠️  No true 'pending' record found. Falling back to look for the absolute newest active WordPress row...\n";
        $fallbackWhere = "visp_data_staging.source_system LIKE 'WordPress%' AND visp_data_staging.deleted = 0";
        $records = $stagingBean->get_full_list('visp_data_staging.date_entered DESC', $fallbackWhere, false, 1);
        
        if (!empty($records)) {
            $record = reset($records);
            echo "⚠️  Found record ID [{$record->id}] but its status is currently '{$record->status}'.\n";
            echo "⚙️  Forcing status to 'pending' in-memory for testing purposes...\n";
            $record->status = 'pending';
        }
    }
}

if (!$record || empty($record->id)) {
    echo "❌ CRITICAL ERROR: Could not find any valid WordPress records in the staging table.\n";
    exit(1);
}

echo "🟢 RECORD VERIFIED ID: " . $record->id . "\n";
echo "🟢 PAYLOAD SOURCE:    " . $record->source_system . "\n";
echo "🟢 RUNTIME STATUS:    " . $record->status . "\n";

// 4. Extract, sanitize, and decode the raw JSON payload data 
$rawDataString = html_entity_decode($record->raw_data, ENT_QUOTES, 'UTF-8');
$decodedPayload = json_decode($rawDataString, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ ERROR: Failed to decode raw_data JSON payload structure: " . json_last_error_msg() . "\n";
    exit(1);
}

// 5. Instantiate and execute the mapper completely in isolation
try {
    echo "🚀 Initializing WordPressVehicleCheckMapper...\n";
    $mapper = new \Custom\DataStaging\Mappers\WordPressVehicleCheckMapper();
    
    echo "⚙️  Executing process() chain...\n";
    $resultMessage = $mapper->process($decodedPayload, $record);
    
    echo "✅ SUCCESS OUTCOME: " . $resultMessage . "\n";
    
    // Only save status updates if we are processing a legitimate pending record
    if (isset($targetId)) {
        echo "ℹ️  Skipping database state save because test was run in explicit single-ID override mode.\n";
    } else {
        $record->status = 'processed';
        $record->save();
        echo "💾 Database state safely updated to 'processed' for record {$record->id}.\n";
    }

} catch (\TypeError $te) {
    echo "💥 CRITICAL PHP TYPE ERROR CAUGHT:\n";
    echo "Message: " . $te->getMessage() . "\n";
    echo "File:    " . $te->getFile() . "\n";
    echo "Line:    " . $te->getLine() . "\n";
    echo "Trace:\n" . $te->getTraceAsString() . "\n";

} catch (\Exception $e) {
    echo "💥 RUNTIME EXCEPTION CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File:    " . $e->getFile() . "\n";
    echo "Line:    " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "=== VINTTRO AUTOMATED MAPPER TEST END ===\n";