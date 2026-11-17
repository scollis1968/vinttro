<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

// Force error printing directly to your terminal window
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load the exact staging scheduler file you edited
require_once 'custom/Extension/modules/Schedulers/Ext/ScheduledTasks/processStaging.php';

global $current_user;
if (empty($current_user)) {
    $current_user = BeanFactory::newBean('Users');
    $current_user->getSystemUser(); // Act as admin
}

echo "=== VINTTRO ISOLATED SCHEDULER LOOP TEST START ===\n";

echo "🚀 Executing processStagingRecords() function directly...\n";

// Execute your scheduler function natively
$result = processStagingRecords();

echo "📊 Function execution finished.\n";
echo "🔄 Returned Result: " . ($result ? "TRUE (Success)" : "FALSE (Failure)") . "\n";

echo "=== VINTTRO ISOLATED SCHEDULER LOOP TEST END ===\n";