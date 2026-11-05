<?php
$module_name = 'visp_vehicle_check';
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
      'useTabs' => true,
      'tabDefs' => 
      array (
        'DEFAULT' => 
        array (
          'newTab' => true,
          'panelDefault' => 'expanded',
        ),
        'LBL_EDITVIEW_PANEL1' => 
        array (
          'newTab' => false,
          'panelDefault' => 'collapsed',
        ),
        'LBL_EDITVIEW_PANEL4' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
        'LBL_EDITVIEW_PANEL2' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
        'LBL_EDITVIEW_PANEL3' => 
        array (
          'newTab' => true,
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
            'name' => 'visp_vehicle_visp_vehicle_check_name',
          ),
          1 => 
          array (
            'name' => 'date_of_check',
            'label' => 'LBL_DATE_OF_CHECK',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'check_by_contact',
            'studio' => 'visible',
            'label' => 'LBL_CHECK_BY_CONTACT',
          ),
          1 => 
          array (
            'name' => 'mileage',
            'label' => 'LBL_MILEAGE',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'check_by',
            'label' => 'LBL_CHECK_BY',
          ),
          1 => '',
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'manual_check_sheet',
            'studio' => 'visible',
            'label' => 'LBL_MANUAL_CHECK_SHEET',
          ),
          1 => 
          array (
            'name' => 'vehicle_clean',
            'studio' => 'visible',
            'label' => 'LBL_VEHICLE_CLEAN',
          ),
        ),
      ),
      'lbl_editview_panel1' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'oil_level_ok',
            'studio' => 'visible',
            'label' => 'LBL_OIL_LEVEL_OK',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'coolant_level_ok',
            'studio' => 'visible',
            'label' => 'LBL_COOLANT_LEVEL_OK',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'wiper_condition_good',
            'studio' => 'visible',
            'label' => 'LBL_WIPER_CONDITION_GOOD',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'indicators_ok',
            'studio' => 'visible',
            'label' => 'LBL_INDICATORS_OK',
          ),
        ),
        4 => 
        array (
          0 => 
          array (
            'name' => 'lights_ok',
            'studio' => 'visible',
            'label' => 'LBL_LIGHTS_OK',
          ),
        ),
        5 => 
        array (
          0 => 
          array (
            'name' => 'horn_ok',
            'studio' => 'visible',
            'label' => 'LBL_HORN_OK',
          ),
        ),
        6 => 
        array (
          0 => 
          array (
            'name' => 'tyre_condition',
            'studio' => 'visible',
            'label' => 'LBL_TYRE_CONDITION',
          ),
        ),
        7 => 
        array (
          0 => 
          array (
            'name' => 'tyre_pressure_ok',
            'studio' => 'visible',
            'label' => 'LBL_TYRE_PRESSURE_OK',
          ),
        ),
        8 => 
        array (
          0 => 
          array (
            'name' => 'brakes_ok',
            'studio' => 'visible',
            'label' => 'LBL_BRAKES_OK',
          ),
        ),
        9 => 
        array (
          0 => 
          array (
            'name' => 'first_aid_kit_ok',
            'studio' => 'visible',
            'label' => 'LBL_FIRST_AID_KIT_OK',
          ),
        ),
        10 => 
        array (
          0 => 
          array (
            'name' => 'any_warning_lights',
            'studio' => 'visible',
            'label' => 'LBL_ANY_WARNING_LIGHTS',
          ),
        ),
      ),
      'lbl_editview_panel4' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'any_issues',
            'studio' => 'visible',
            'label' => 'LBL_ANY_ISSUES',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'issue_description',
            'studio' => 'visible',
            'label' => 'LBL_ISSUE_DESCRIPTION',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'image_of_dash',
            'studio' => 'visible',
            'label' => 'LBL_IMAGE_OF_DASH',
          ),
        ),
      ),
      'lbl_editview_panel2' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'any_damage',
            'studio' => 'visible',
            'label' => 'LBL_ANY_DAMAGE',
          ),
          1 => '',
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'damage_description',
            'studio' => 'visible',
            'label' => 'LBL_DAMAGE_DESCRIPTION',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'image_of_damage',
            'studio' => 'visible',
            'label' => 'LBL_IMAGE_OF_DAMAGE',
          ),
          1 => '',
        ),
      ),
      'lbl_editview_panel3' => 
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
            'name' => 'assigned_user_name',
            'label' => 'LBL_ASSIGNED_TO_NAME',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'created_by_name',
            'label' => 'LBL_CREATED',
          ),
          1 => 
          array (
            'name' => 'date_entered',
            'comment' => 'Date record created',
            'label' => 'LBL_DATE_ENTERED',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'modified_by_name',
            'label' => 'LBL_MODIFIED_NAME',
          ),
          1 => 
          array (
            'name' => 'date_modified',
            'comment' => 'Date record last modified',
            'label' => 'LBL_DATE_MODIFIED',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'description',
            'comment' => 'Full text of the note',
            'label' => 'LBL_DESCRIPTION',
          ),
          1 => '',
        ),
        4 => 
        array (
          0 => 
          array (
            'name' => 'any_damage_or_issues',
            'studio' => 'visible',
            'label' => 'LBL_ANY_DAMAGE_OR_ISSUES',
          ),
        ),
        5 => 
        array (
          0 => 
          array (
            'name' => 'oil_level_comment',
            'label' => 'LBL_OIL_LEVEL_COMMENT',
          ),
          1 => 
          array (
            'name' => 'coolant_level_comment',
            'label' => 'LBL_COOLANT_LEVEL_COMMENT',
          ),
        ),
        6 => 
        array (
          0 => 
          array (
            'name' => 'brakes_comment',
            'label' => 'LBL_BRAKES_COMMENT',
          ),
        ),
        7 => 
        array (
          0 => 
          array (
            'name' => 'horn_comment',
            'label' => 'LBL_HORN_COMMENT',
          ),
        ),
        8 => 
        array (
          0 => 
          array (
            'name' => 'indicators_comment',
            'label' => 'LBL_INDICATORS_COMMENT',
          ),
        ),
        9 => 
        array (
          0 => 
          array (
            'name' => 'lights_comment',
            'label' => 'LBL_LIGHTS_COMMENT',
          ),
        ),
        10 => 
        array (
          0 => 
          array (
            'name' => 'tyre_condition_comment',
            'label' => 'LBL_TYRE_CONDITION_COMMENT',
          ),
        ),
        11 => 
        array (
          0 => 
          array (
            'name' => 'tyre_pressure_comment',
            'label' => 'LBL_TYRE_PRESSURE_COMMENT',
          ),
        ),
        12 => 
        array (
          0 => 
          array (
            'name' => 'wiper_condition_comment',
            'label' => 'LBL_WIPER_CONDITION_COMMENT',
          ),
        ),
        13 => 
        array (
          0 => 
          array (
            'name' => 'first_aid_kit_comment',
            'label' => 'LBL_FIRST_AID_KIT_COMMENT',
          ),
        ),
        14 => 
        array (
          0 => 
          array (
            'name' => 'vehicle_clean_comments',
            'label' => 'LBL_VEHICLE_CLEAN_COMMENTS',
          ),
        ),
        15 => 
        array (
          0 => 
          array (
            'name' => 'warning_light_description',
            'studio' => 'visible',
            'label' => 'LBL_WARNING_LIGHT_DESCRIPTION',
          ),
          1 => '',
        ),
      ),
    ),
  ),
);
