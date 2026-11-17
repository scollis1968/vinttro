<?php
// created: 2026-11-05 07:02:59
$dictionary["visp_vehicle_visp_vehicle_issue"] = array (
  'true_relationship_type' => 'one-to-many',
  'relationships' => 
  array (
    'visp_vehicle_visp_vehicle_issue' => 
    array (
      'lhs_module' => 'visp_vehicle',
      'lhs_table' => 'visp_vehicle',
      'lhs_key' => 'id',
      'rhs_module' => 'visp_vehicle_issue',
      'rhs_table' => 'visp_vehicle_issue',
      'rhs_key' => 'id',
      'relationship_type' => 'many-to-many',
      'join_table' => 'visp_vehicle_visp_vehicle_issue_c',
      'join_key_lhs' => 'visp_vehicle_visp_vehicle_issuevisp_vehicle_ida',
      'join_key_rhs' => 'visp_vehicle_visp_vehicle_issuevisp_vehicle_issue_idb',
    ),
  ),
  'table' => 'visp_vehicle_visp_vehicle_issue_c',
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
      'name' => 'visp_vehicle_visp_vehicle_issuevisp_vehicle_ida',
      'type' => 'varchar',
      'len' => 36,
    ),
    4 => 
    array (
      'name' => 'visp_vehicle_visp_vehicle_issuevisp_vehicle_issue_idb',
      'type' => 'varchar',
      'len' => 36,
    ),
  ),
  'indices' => 
  array (
    0 => 
    array (
      'name' => 'visp_vehicle_visp_vehicle_issuespk',
      'type' => 'primary',
      'fields' => 
      array (
        0 => 'id',
      ),
    ),
    1 => 
    array (
      'name' => 'visp_vehicle_visp_vehicle_issue_ida1',
      'type' => 'index',
      'fields' => 
      array (
        0 => 'visp_vehicle_visp_vehicle_issuevisp_vehicle_ida',
      ),
    ),
    2 => 
    array (
      'name' => 'visp_vehicle_visp_vehicle_issue_alt',
      'type' => 'alternate_key',
      'fields' => 
      array (
        0 => 'visp_vehicle_visp_vehicle_issuevisp_vehicle_issue_idb',
      ),
    ),
  ),
);