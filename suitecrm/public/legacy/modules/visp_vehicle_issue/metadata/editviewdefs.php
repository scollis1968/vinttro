<?php
$module_name = 'visp_vehicle_issue';
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
        'LBL_EDITVIEW_PANEL2' => 
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
          0 => 'name',
          1 => 
          array (
            'name' => 'vehicle',
            'studio' => 'visible',
            'label' => 'LBL_VEHICLE',
          ),
        ),
        1 => 
        array (
          0 => 'description',
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'severity',
            'studio' => 'visible',
            'label' => 'LBL_SEVERITY',
          ),
          1 => 
          array (
            'name' => 'visp_vehicle_visp_vehicle_issue_name',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'visp_vehicle_check_visp_vehicle_issue_name',
          ),
        ),
      ),
      'lbl_editview_panel1' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'reported_by',
            'studio' => 'visible',
            'label' => 'LBL_REPORTED_BY',
          ),
          1 => 
          array (
            'name' => 'reported_date',
            'label' => 'LBL_REPORTED_DATE',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'reported_check',
            'studio' => 'visible',
            'label' => 'LBL_REPORTED_CHECK',
          ),
        ),
      ),
      'lbl_editview_panel2' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'resolved_by',
            'studio' => 'visible',
            'label' => 'LBL_RESOLVED_BY',
          ),
          1 => 
          array (
            'name' => 'date_resolved',
            'label' => 'LBL_DATE_RESOLVED',
          ),
        ),
      ),
    ),
  ),
);
