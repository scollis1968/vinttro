<?php
$hook_array['after_save'][] = array(
    10, // Fires after native saving operations.
    'Sync Fleet Admins on Vehicle Change',
    'custom/include/Vinttro/WPSyncHook.php',
    'VinttroWPSyncMaster',
    'syncVehicleFleetUpdate'
);