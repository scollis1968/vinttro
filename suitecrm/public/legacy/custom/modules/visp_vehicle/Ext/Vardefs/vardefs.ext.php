<?php 
 //WARNING: The contents of this file are auto-generated


// created: 2026-11-05 07:02:59
$dictionary["visp_vehicle"]["fields"]["visp_vehicle_visp_vehicle_check"] = array (
  'name' => 'visp_vehicle_visp_vehicle_check',
  'type' => 'link',
  'relationship' => 'visp_vehicle_visp_vehicle_check',
  'source' => 'non-db',
  'module' => 'visp_vehicle_check',
  'bean_name' => 'visp_vehicle_check',
  'side' => 'right',
  'vname' => 'LBL_VISP_VEHICLE_VISP_VEHICLE_CHECK_FROM_VISP_VEHICLE_CHECK_TITLE',
);


// created: 2026-11-05 07:02:59
$dictionary["visp_vehicle"]["fields"]["visp_fleet_visp_vehicle"] = array (
  'name' => 'visp_fleet_visp_vehicle',
  'type' => 'link',
  'relationship' => 'visp_fleet_visp_vehicle',
  'source' => 'non-db',
  'module' => 'visp_fleet',
  'bean_name' => 'visp_fleet',
  'vname' => 'LBL_VISP_FLEET_VISP_VEHICLE_FROM_VISP_FLEET_TITLE',
  'id_name' => 'visp_fleet_visp_vehiclevisp_fleet_ida',
);
$dictionary["visp_vehicle"]["fields"]["visp_fleet_visp_vehicle_name"] = array (
  'name' => 'visp_fleet_visp_vehicle_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_FLEET_VISP_VEHICLE_FROM_VISP_FLEET_TITLE',
  'save' => true,
  'id_name' => 'visp_fleet_visp_vehiclevisp_fleet_ida',
  'link' => 'visp_fleet_visp_vehicle',
  'table' => 'visp_fleet',
  'module' => 'visp_fleet',
  'rname' => 'name',
);
$dictionary["visp_vehicle"]["fields"]["visp_fleet_visp_vehiclevisp_fleet_ida"] = array (
  'name' => 'visp_fleet_visp_vehiclevisp_fleet_ida',
  'type' => 'link',
  'relationship' => 'visp_fleet_visp_vehicle',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_FLEET_VISP_VEHICLE_FROM_VISP_VEHICLE_TITLE',
);


// created: 2026-11-05 07:02:59
$dictionary["visp_vehicle"]["fields"]["visp_vehicle_visp_vehicle_issue"] = array (
  'name' => 'visp_vehicle_visp_vehicle_issue',
  'type' => 'link',
  'relationship' => 'visp_vehicle_visp_vehicle_issue',
  'source' => 'non-db',
  'module' => 'visp_vehicle_issue',
  'bean_name' => 'visp_vehicle_issue',
  'side' => 'right',
  'vname' => 'LBL_VISP_VEHICLE_VISP_VEHICLE_ISSUE_FROM_VISP_VEHICLE_ISSUE_TITLE',
);


// created: 2026-11-05 07:02:59
$dictionary["visp_vehicle"]["fields"]["visp_vehicle_notes"] = array (
  'name' => 'visp_vehicle_notes',
  'type' => 'link',
  'relationship' => 'visp_vehicle_notes',
  'source' => 'non-db',
  'module' => 'Notes',
  'bean_name' => 'Note',
  'side' => 'right',
  'vname' => 'LBL_VISP_VEHICLE_NOTES_FROM_NOTES_TITLE',
);

?>