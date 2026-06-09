<?php
$hook_array['before_save'][] = array(
    1,
    'Populate Check Name & Update Vehicle Service Metrics',
    'custom/modules/visp_vehicle_check/VehicleCheckHook.php',
    'VehicleCheckHook',
    'beforeSaveMethod'
);