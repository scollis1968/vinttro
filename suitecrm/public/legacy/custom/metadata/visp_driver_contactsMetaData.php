<?php
// created: 2026-11-05 07:03:00
$dictionary["visp_driver_contacts"] = array (
  'true_relationship_type' => 'one-to-one',
  'relationships' => 
  array (
    'visp_driver_contacts' => 
    array (
      'lhs_module' => 'visp_driver',
      'lhs_table' => 'visp_driver',
      'lhs_key' => 'id',
      'rhs_module' => 'Contacts',
      'rhs_table' => 'contacts',
      'rhs_key' => 'id',
      'relationship_type' => 'many-to-many',
      'join_table' => 'visp_driver_contacts_c',
      'join_key_lhs' => 'visp_driver_contactsvisp_driver_ida',
      'join_key_rhs' => 'visp_driver_contactscontacts_idb',
    ),
  ),
  'table' => 'visp_driver_contacts_c',
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
      'name' => 'visp_driver_contactsvisp_driver_ida',
      'type' => 'varchar',
      'len' => 36,
    ),
    4 => 
    array (
      'name' => 'visp_driver_contactscontacts_idb',
      'type' => 'varchar',
      'len' => 36,
    ),
  ),
  'indices' => 
  array (
    0 => 
    array (
      'name' => 'visp_driver_contactsspk',
      'type' => 'primary',
      'fields' => 
      array (
        0 => 'id',
      ),
    ),
    1 => 
    array (
      'name' => 'visp_driver_contacts_ida1',
      'type' => 'index',
      'fields' => 
      array (
        0 => 'visp_driver_contactsvisp_driver_ida',
      ),
    ),
    2 => 
    array (
      'name' => 'visp_driver_contacts_idb2',
      'type' => 'index',
      'fields' => 
      array (
        0 => 'visp_driver_contactscontacts_idb',
      ),
    ),
  ),
);