<?php
// created: 2026-11-05 07:02:59
$dictionary["visp_lead_am_projecttemplates"] = array (
  'true_relationship_type' => 'many-to-many',
  'relationships' => 
  array (
    'visp_lead_am_projecttemplates' => 
    array (
      'lhs_module' => 'visp_lead',
      'lhs_table' => 'visp_lead',
      'lhs_key' => 'id',
      'rhs_module' => 'AM_ProjectTemplates',
      'rhs_table' => 'am_projecttemplates',
      'rhs_key' => 'id',
      'relationship_type' => 'many-to-many',
      'join_table' => 'visp_lead_am_projecttemplates_c',
      'join_key_lhs' => 'visp_lead_am_projecttemplatesvisp_lead_ida',
      'join_key_rhs' => 'visp_lead_am_projecttemplatesam_projecttemplates_idb',
    ),
  ),
  'table' => 'visp_lead_am_projecttemplates_c',
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
      'name' => 'visp_lead_am_projecttemplatesvisp_lead_ida',
      'type' => 'varchar',
      'len' => 36,
    ),
    4 => 
    array (
      'name' => 'visp_lead_am_projecttemplatesam_projecttemplates_idb',
      'type' => 'varchar',
      'len' => 36,
    ),
  ),
  'indices' => 
  array (
    0 => 
    array (
      'name' => 'visp_lead_am_projecttemplatesspk',
      'type' => 'primary',
      'fields' => 
      array (
        0 => 'id',
      ),
    ),
    1 => 
    array (
      'name' => 'visp_lead_am_projecttemplates_alt',
      'type' => 'alternate_key',
      'fields' => 
      array (
        0 => 'visp_lead_am_projecttemplatesvisp_lead_ida',
        1 => 'visp_lead_am_projecttemplatesam_projecttemplates_idb',
      ),
    ),
  ),
);