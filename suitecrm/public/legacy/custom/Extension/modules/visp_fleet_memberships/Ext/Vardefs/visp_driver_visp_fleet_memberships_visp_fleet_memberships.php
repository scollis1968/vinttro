<?php
// created: 2026-11-05 07:03:00
$dictionary["visp_fleet_memberships"]["fields"]["visp_driver_visp_fleet_memberships"] = array (
  'name' => 'visp_driver_visp_fleet_memberships',
  'type' => 'link',
  'relationship' => 'visp_driver_visp_fleet_memberships',
  'source' => 'non-db',
  'module' => 'visp_driver',
  'bean_name' => 'visp_driver',
  'vname' => 'LBL_VISP_DRIVER_VISP_FLEET_MEMBERSHIPS_FROM_VISP_DRIVER_TITLE',
  'id_name' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_driver_visp_fleet_memberships_name"] = array (
  'name' => 'visp_driver_visp_fleet_memberships_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_DRIVER_VISP_FLEET_MEMBERSHIPS_FROM_VISP_DRIVER_TITLE',
  'save' => true,
  'id_name' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
  'link' => 'visp_driver_visp_fleet_memberships',
  'table' => 'visp_driver',
  'module' => 'visp_driver',
  'rname' => 'name',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_driver_visp_fleet_membershipsvisp_driver_ida"] = array (
  'name' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
  'type' => 'link',
  'relationship' => 'visp_driver_visp_fleet_memberships',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_DRIVER_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
);
