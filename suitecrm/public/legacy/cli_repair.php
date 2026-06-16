<?php
// Ensure this script can only run via command line
if (php_sapi_name() !== 'cli') {
    die("This script can only be run via CLI.\n");
}

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

echo "🔄 Running Legacy Quick Repair and Rebuild...\n";

$repair = new RepairAndClear();
// Focus specifically on compiling extensions and clearing metadata safely
$repair->repairAndClearAll(array('clearAll'), array('All Modules'), true, false);

echo "✅ Compiled extensions and rebuilt logic hooks successfully.\n";