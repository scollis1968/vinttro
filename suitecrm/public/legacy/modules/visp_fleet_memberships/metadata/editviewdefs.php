<?php
$module_name = 'visp_fleet_memberships';
$viewdefs [$module_name] = 
array (
  'EditView' => 
  array (
    'templateMeta' => 
    array (
      'maxColumns' => '2',
      'widths' => 
      array (
        0 => 
        array (
          'label' => '10',
          'field' => '30',
        ),
        1 => 
        array (
          'label' => '10',
          'field' => '30',
        ),
      ),
      'useTabs' => false,
      'tabDefs' => 
      array (
        'DEFAULT' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
        'LBL_EDITVIEW_PANEL1' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
      ),
      'syncDetailEditViews' => true,
    ),
    'panels' => 
    array (
      'default' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'visp_fleet_visp_fleet_memberships_name',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'visp_fleet_memberships_contacts_name',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'is_driver',
            'label' => 'LBL_IS_DRIVER',
          ),
          1 => 
          array (
            'name' => 'is_fleetadmin',
            'label' => 'LBL_IS_FLEETADMIN',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'active_from',
            'label' => 'LBL_ACTIVE_FROM',
          ),
          1 => 
          array (
            'name' => 'active_to',
            'label' => 'LBL_ACTIVE_TO',
          ),
        ),
      ),
      'lbl_editview_panel1' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'visp_driver_visp_fleet_memberships_name',
          ),
        ),
      ),
    ),
  ),
);
