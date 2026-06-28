<?php
$hook_array['after_save'][] = array(
    10, 
    'Sync Fleet Admins on Vehicle Change',
    'custom/include/Vinttro/WPSyncHook.php',
    'VinttroWPSyncMaster', // Changed class name here
    'syncVehicleFleetUpdate'
);