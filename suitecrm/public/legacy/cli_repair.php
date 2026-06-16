<?php
if (php_sapi_name() !== 'cli') {
    die("This script can only be run via CLI.\n");
}

// 🪚 FIX PATH TRAPS: Lock the working directory to public/legacy so includes resolve perfectly
chdir(__DIR__);

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

// 🛠️ INITIALIZE GLOBALS: Populate the language states that the core repair framework expects
global $sugar_config, $current_language, $app_list_strings, $app_strings, $current_user;

if (empty($current_language)) {
    $current_language = $sugar_config['default_language'] ?? 'en_us';
}

$app_list_strings = return_app_list_strings_language($current_language);
$app_strings = return_application_language($current_language);

// 👤 MOCK ACCESS CONTEXT: Authenticate a system user to pass backend ACL checks
$current_user = new User();
$current_user->getSystemUser();

echo "🔄 Running Legacy Quick Repair and Rebuild with language contexts securely wrapped...\n";

$repair = new RepairAndClear();
$repair->repairAndClearAll(array('clearAll'), array('All Modules'), true, false);

echo "✅ Compiled extensions and rebuilt logic hooks successfully.\n";