<?php
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
