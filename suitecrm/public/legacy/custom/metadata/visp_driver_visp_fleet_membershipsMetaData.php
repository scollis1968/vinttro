<?php
// created: 2026-11-05 07:03:00
$dictionary["visp_driver_visp_fleet_memberships"] = array (
  'true_relationship_type' => 'one-to-many',
  'relationships' => 
  array (
    'visp_driver_visp_fleet_memberships' => 
    array (
      'lhs_module' => 'visp_driver',
      'lhs_table' => 'visp_driver',
      'lhs_key' => 'id',
      'rhs_module' => 'visp_fleet_memberships',
      'rhs_table' => 'visp_fleet_memberships',
      'rhs_key' => 'id',
      'relationship_type' => 'many-to-many',
      'join_table' => 'visp_driver_visp_fleet_memberships_c',
      'join_key_lhs' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
      'join_key_rhs' => 'visp_driver_visp_fleet_membershipsvisp_fleet_memberships_idb',
    ),
  ),
  'table' => 'visp_driver_visp_fleet_memberships_c',
  'fields' => 
  array (
    0 => 
    array (
      'name' => 'id',
      'type' => 'varchar',
      'len' => 36,
    ),
    1 => 
    array (
      'name' => 'date_modified',
      'type' => 'datetime',
    ),
    2 => 
    array (
      'name' => 'deleted',
      'type' => 'bool',
      'len' => '1',
      'default' => '0',
      'required' => true,
    ),
    3 => 
    array (
      'name' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
      'type' => 'varchar',
      'len' => 36,
    ),
    4 => 
    array (
      'name' => 'visp_driver_visp_fleet_membershipsvisp_fleet_memberships_idb',
      'type' => 'varchar',
      'len' => 36,
    ),
  ),
  'indices' => 
  array (
    0 => 
    array (
      'name' => 'visp_driver_visp_fleet_membershipsspk',
      'type' => 'primary',
      'fields' => 
      array (
        0 => 'id',
      ),
    ),
    1 => 
    array (
      'name' => 'visp_driver_visp_fleet_memberships_ida1',
      'type' => 'index',
      'fields' => 
      array (
        0 => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
      ),
    ),
    2 => 
    array (
      'name' => 'visp_driver_visp_fleet_memberships_alt',
      'type' => 'alternate_key',
      'fields' => 
      array (
        0 => 'visp_driver_visp_fleet_membershipsvisp_fleet_memberships_idb',
      ),
    ),
  ),
);