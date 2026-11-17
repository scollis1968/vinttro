<?php
$popupMeta = array (
    'moduleMain' => 'visp_vehicle',
    'varName' => 'visp_vehicle',
    'orderBy' => 'visp_vehicle.name',
    'whereClauses' => array (
  'registration_number' => 'visp_vehicle.registration_number',
  'main_driver' => 'visp_vehicle.main_driver',
),
    'searchInputs' => array (
  4 => 'registration_number',
  5 => 'main_driver',
),
    'searchdefs' => array (
  'registration_number' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_REGISTRATION_NUMBER',
    'width' => '10%',
    'name' => 'registration_number',
  ),
  'main_driver' => 
  array (
    'type' => 'relate',
    'studio' => 'visible',
    'label' => 'LBL_MAIN_DRIVER',
    'id' => 'VIPS_DRIVER_ID_C',
    'link' => true,
    'width' => '10%',
    'name' => 'main_driver',
  ),
),
    'listviewdefs' => array (
),
);
