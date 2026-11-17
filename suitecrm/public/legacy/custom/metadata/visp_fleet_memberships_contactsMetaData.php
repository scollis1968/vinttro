<?php
// created: 2026-11-05 07:02:59
$dictionary["visp_fleet_memberships_contacts"] = array (
  'true_relationship_type' => 'one-to-many',
  'relationships' => 
  array (
    'visp_fleet_memberships_contacts' => 
    array (
      'lhs_module' => 'Contacts',
      'lhs_table' => 'contacts',
      'lhs_key' => 'id',
      'rhs_module' => 'visp_fleet_memberships',
      'rhs_table' => 'visp_fleet_memberships',
      'rhs_key' => 'id',
      'relationship_type' => 'many-to-many',
      'join_table' => 'visp_fleet_memberships_contacts_c',
      'join_key_lhs' => 'visp_fleet_memberships_contactscontacts_ida',
      'join_key_rhs' => 'visp_fleet_memberships_contactsvisp_fleet_memberships_idb',
    ),
  ),
  'table' => 'visp_fleet_memberships_contacts_c',
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
      'name' => 'visp_fleet_memberships_contactscontacts_ida',
      'type' => 'varchar',
      'len' => 36,
    ),
    4 => 
    array (
      'name' => 'visp_fleet_memberships_contactsvisp_fleet_memberships_idb',
      'type' => 'varchar',
      'len' => 36,
    ),
  ),
  'indices' => 
  array (
    0 => 
    array (
      'name' => 'visp_fleet_memberships_contactsspk',
      'type' => 'primary',
      'fields' => 
      array (
        0 => 'id',
      ),
    ),
    1 => 
    array (
      'name' => 'visp_fleet_memberships_contacts_ida1',
      'type' => 'index',
      'fields' => 
      array (
        0 => 'visp_fleet_memberships_contactscontacts_ida',
      ),
    ),
    2 => 
    array (
      'name' => 'visp_fleet_memberships_contacts_alt',
      'type' => 'alternate_key',
      'fields' => 
      array (
        0 => 'visp_fleet_memberships_contactsvisp_fleet_memberships_idb',
      ),
    ),
  ),
);