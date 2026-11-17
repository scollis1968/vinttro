<?php 
 //WARNING: The contents of this file are auto-generated


// Auto-generated hook declaration
$hook_array['after_save'][] = array(
    10,
    'Auto Create and Link Driver Record',
    'custom/modules/Contacts/ContactsDriverHook.php',
    'ContactsDriverHook',
    'handleDriverRelation'
);

$hook_array['after_save'][] = array(
    1,
    'Sync Contact to WordPress',
    'custom/modules/Contacts/WPSyncHook.php',
    'WPSyncHook',
    'syncToWordpress'
);
?>