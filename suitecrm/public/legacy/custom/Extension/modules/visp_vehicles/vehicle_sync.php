<?php
$hook_array['after_save'][] = array(
    1,
    'Sync Fleet/Contact on Vehicle Update',
    'custom/include/Vinttro/WPSyncHook.php', // Reuse your existing class file path
    'WPSyncHook',
    'syncVehicleHook'
);