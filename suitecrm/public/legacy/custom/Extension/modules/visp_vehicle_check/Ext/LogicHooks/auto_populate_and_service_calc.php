<?php
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$hook_array['before_save'][] = array(
    1,
    'Populate Check Name & Update Vehicle Service Metrics on Edit',
    'custom/include/Vinttro/VehicleCheckHook.php', // Updated path
    'VehicleCheckHook',
    'beforeSaveMethod'
);

$hook_array['after_relationship_add'][] = array(
    1,
    'Populate Check Name & Update Vehicle Service Metrics on Creation',
    'custom/include/Vinttro/VehicleCheckHook.php', // Updated path
    'VehicleCheckHook',
    'afterRelationshipAddMethod'
);