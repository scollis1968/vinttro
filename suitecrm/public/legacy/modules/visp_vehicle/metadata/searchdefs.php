<?php
$module_name = 'visp_vehicle';
$searchdefs [$module_name] = 
array (
  'layout' => 
  array (
    'basic_search' => 
    array (
      'visp_fleet_visp_vehicle_name' => 
      array (
        'type' => 'relate',
        'link' => true,
        'label' => 'LBL_VISP_FLEET_VISP_VEHICLE_FROM_VISP_FLEET_TITLE',
        'id' => 'VISP_FLEET_VISP_VEHICLEVISP_FLEET_IDA',
        'width' => '10%',
        'default' => true,
        'name' => 'visp_fleet_visp_vehicle_name',
      ),
      'make' => 
      array (
        'type' => 'varchar',
        'label' => 'LBL_MAKE',
        'width' => '10%',
        'default' => true,
        'name' => 'make',
      ),
    ),
    'advanced_search' => 
    array (
    ),
  ),
  'templateMeta' => 
  array (
    'maxColumns' => '3',
    'maxColumnsBasic' => '4',
    'widths' => 
    array (
      'label' => '10',
      'field' => '30',
    ),
  ),
);
