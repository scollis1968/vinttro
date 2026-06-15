<?php
$hook_array['before_save'][] = array(
    1,
    'Populate Check Name & Update Vehicle Service Metrics on Edit',
    'custom/modules/visp_vehicle_check/VehicleCheckHook.php',
    'VehicleCheckHook',
    'beforeSaveMethod'
);

$hook_array['after_relationship_add'][] = array(
    1,
    'Populate Check Name & Update Vehicle Service Metrics on Creation',
    'custom/modules/visp_vehicle_check/VehicleCheckHook.php',
    'VehicleCheckHook',
    'afterRelationshipAddMethod'
);