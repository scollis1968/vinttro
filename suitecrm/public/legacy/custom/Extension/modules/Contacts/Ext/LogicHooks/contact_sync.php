<?php
$hook_array['after_save'][] = array(
    1,
    'Sync Contact to WordPress',
    'custom/modules/Contacts/WPSyncHook.php',
    'WPSyncHook',
    'syncToWordpress'
);