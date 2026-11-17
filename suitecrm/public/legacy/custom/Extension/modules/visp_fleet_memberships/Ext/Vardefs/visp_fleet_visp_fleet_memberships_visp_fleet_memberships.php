<?php
// created: 2026-11-05 07:03:00
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_visp_fleet_memberships"] = array (
  'name' => 'visp_fleet_visp_fleet_memberships',
  'type' => 'link',
  'relationship' => 'visp_fleet_visp_fleet_memberships',
  'source' => 'non-db',
  'module' => 'visp_fleet',
  'bean_name' => 'visp_fleet',
  'vname' => 'LBL_VISP_FLEET_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_TITLE',
  'id_name' => 'visp_fleet_visp_fleet_membershipsvisp_fleet_ida',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_visp_fleet_memberships_name"] = array (
  'name' => 'visp_fleet_visp_fleet_memberships_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_FLEET_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_TITLE',
  'save' => true,
  'id_name' => 'visp_fleet_visp_fleet_membershipsvisp_fleet_ida',
  'link' => 'visp_fleet_visp_fleet_memberships',
  'table' => 'visp_fleet',
  'module' => 'visp_fleet',
  'rname' => 'name',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_visp_fleet_membershipsvisp_fleet_ida"] = array (
  'name' => 'visp_fleet_visp_fleet_membershipsvisp_fleet_ida',
  'type' => 'link',
  'relationship' => 'visp_fleet_visp_fleet_memberships',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_FLEET_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
);
