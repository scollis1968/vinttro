<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';
require_once 'custom/include/DataStaging/StagingMapperInterface.php';
require_once 'custom/include/DataStaging/AbstractStagingMapper.php';
require_once 'custom/include/DataStaging/Mappers/WordPressVehicleCheckMapper.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

global $current_user;
if (empty($current_user)) {
    $current_user = BeanFactory::newBean('Users');
    $current_user->getSystemUser();
}

echo "=== VINTTRO UNADULTERATED TRUTH TEST START ===\n";

// Target your known record from the UI DOM
$targetId = 'e42a74db-b592-4ce8-846c-0f5cbe0a75c5';
$record = BeanFactory::getBean('visp_data_staging', $targetId);

if (!$record || empty($record->id)) {
    echo "❌ CRITICAL ERROR: Record '{$targetId}' could not be found.\n";
    exit(1);
}

echo "📊 --- TRUE DATABASE VALUES STORED IN DISK ---\n";
echo "🟢 RECORD ID:         " . $record->id . "\n";
echo "🟢 RECORD NAME:       " . $record->name . "\n";
echo "📊 TRUE SOURCE SYSTEM: [" . $record->source_system . "]\n"; // Is it lowercase? Is it a key?
echo "📊 TRUE CORE STATUS:   [" . $record->status . "]\n";        // Is it actually 'pending'?

echo "\n🚀 Testing Staging Record Save Lifecycle...\n";
try {
    // Attempt to save the record to see if a custom Logic Hook or Workflow on the Data Staging module crashes or loops
    echo "⚙️  Triggering \$record->save()...\n";
    $record->save();
    echo "✅ SUCCESS: Staging record saved cleanly without crashing!\n";
} catch (\Throwable $t) {
    echo "💥 CRITICAL LIVE SAVE CRASH CAUGHT:\n";
    echo "Message: " . $t->getMessage() . "\n";
    echo "File:    " . $t->getFile() . "\n";
    echo "Line:    " . $t->getLine() . "\n";
    exit(1);
}

echo "=== VINTTRO UNADULTERATED TRUTH TEST END ===\n";