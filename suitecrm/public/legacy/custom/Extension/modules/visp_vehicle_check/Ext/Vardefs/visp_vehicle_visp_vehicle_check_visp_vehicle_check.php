<?php
// created: 2026-11-05 07:02:59
$dictionary["visp_vehicle_check"]["fields"]["visp_vehicle_visp_vehicle_check"] = array (
  'name' => 'visp_vehicle_visp_vehicle_check',
  'type' => 'link',
  'relationship' => 'visp_vehicle_visp_vehicle_check',
  'source' => 'non-db',
  'module' => 'visp_vehicle',
  'bean_name' => 'visp_vehicle',
  'vname' => 'LBL_VISP_VEHICLE_VISP_VEHICLE_CHECK_FROM_VISP_VEHICLE_TITLE',
  'id_name' => 'visp_vehicle_visp_vehicle_checkvisp_vehicle_ida',
);
$dictionary["visp_vehicle_check"]["fields"]["visp_vehicle_visp_vehicle_check_name"] = array (
  'name' => 'visp_vehicle_visp_vehicle_check_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_VEHICLE_VISP_VEHICLE_CHECK_FROM_VISP_VEHICLE_TITLE',
  'save' => true,
  'id_name' => 'visp_vehicle_visp_vehicle_checkvisp_vehicle_ida',
  'link' => 'visp_vehicle_visp_vehicle_check',
  'table' => 'visp_vehicle',
  'module' => 'visp_vehicle',
  'rname' => 'name',
);
$dictionary["visp_vehicle_check"]["fields"]["visp_vehicle_visp_vehicle_checkvisp_vehicle_ida"] = array (
  'name' => 'visp_vehicle_visp_vehicle_checkvisp_vehicle_ida',
  'type' => 'link',
  'relationship' => 'visp_vehicle_visp_vehicle_check',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_VEHICLE_VISP_VEHICLE_CHECK_FROM_VISP_VEHICLE_CHECK_TITLE',
);
