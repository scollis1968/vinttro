<?php
$module_name = 'visp_vehicle';
$viewdefs [$module_name] = 
array (
  'DetailView' => 
  array (
    'templateMeta' => 
    array (
      'form' => 
      array (
        'buttons' => 
        array (
          0 => 'EDIT',
          1 => 'DUPLICATE',
          2 => 'DELETE',
          3 => 'FIND_DUPLICATES',
        ),
      ),
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
            'name' => 'name',
            'label' => 'LBL_NAME',
          ),
          1 => 
          array (
            'name' => 'vin',
            'label' => 'LBL_VIN',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'make',
            'label' => 'LBL_MAKE',
          ),
          1 => 
          array (
            'name' => 'model',
            'label' => 'LBL_MODEL',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'variant',
            'label' => 'LBL_VARIANT',
          ),
          1 => '',
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'date_purchase',
            'label' => 'LBL_DATE_PURCHASE',
          ),
          1 => 
          array (
            'name' => 'date_registartion',
            'label' => 'LBL_DATE_REGISTARTION',
          ),
        ),
        4 => 
        array (
          0 => 
          array (
            'name' => 'visp_fleet_visp_vehicle_name',
            'label' => 'LBL_VISP_FLEET_VISP_VEHICLE_FROM_VISP_FLEET_TITLE',
          ),
        ),
        5 => 
        array (
          0 => 
          array (
            'name' => 'main_image',
            'studio' => 'visible',
            'label' => 'LBL_MAIN_IMAGE',
          ),
        ),
        6 => 
        array (
          0 => 
          array (
            'name' => 'v5',
            'studio' => 'visible',
            'label' => 'LBL_V5',
          ),
          1 => '',
        ),
        7 => 
        array (
          0 => 
          array (
            'name' => 'main_driver',
            'studio' => 'visible',
            'label' => 'LBL_MAIN_DRIVER',
          ),
          1 => 
          array (
            'name' => 'coordinator',
            'studio' => 'visible',
            'label' => 'LBL_COORDINATOR',
          ),
        ),
      ),
      'lbl_editview_panel1' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'date_last_service',
            'label' => 'LBL_DATE_LAST_SERVICE',
          ),
          1 => 
          array (
            'name' => 'date_next_service',
            'label' => 'LBL_DATE_NEXT_SERVICE',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'date_last_mot',
            'label' => 'LBL_DATE_LAST_MOT',
          ),
          1 => 
          array (
            'name' => 'date_next_mot',
            'label' => 'LBL_DATE_NEXT_MOT',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'date_last_tax',
            'label' => 'LBL_DATE_LAST_TAX',
          ),
          1 => 
          array (
            'name' => 'date_next_tax',
            'label' => 'LBL_DATE_NEXT_TAX',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'date_last_check',
            'label' => 'LBL_DATE_LAST_CHECK',
          ),
          1 => '',
        ),
        4 => 
        array (
          0 => 
          array (
            'name' => 'mileage_last_check',
            'label' => 'LBL_MILEAGE_LAST_CHECK',
          ),
          1 => 
          array (
            'name' => 'mileage_last_service',
            'label' => 'LBL_MILEAGE_LAST_SERVICE',
          ),
        ),
        5 => 
        array (
          0 => 
          array (
            'name' => 'mileage_last_mot',
            'label' => 'LBL_MILEAGE_LAST_MOT',
          ),
          1 => '',
        ),
        6 => 
        array (
          0 => 
          array (
            'name' => 'warranty_end_date',
            'label' => 'LBL_WARRANTY_END_DATE',
          ),
          1 => 
          array (
            'name' => 'warranty_end_miles',
            'label' => 'LBL_WARRANTY_END_MILES',
          ),
        ),
        7 => 
        array (
          0 => 
          array (
            'name' => 'service_interval_miles',
            'label' => 'LBL_SERVICE_INTERVAL_MILES',
          ),
          1 => 
          array (
            'name' => 'service_interval_months',
            'label' => 'LBL_SERVICE_INTERVAL_MONTHS',
          ),
        ),
      ),
    ),
  ),
);
