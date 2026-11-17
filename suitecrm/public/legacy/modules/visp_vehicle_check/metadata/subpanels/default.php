<?php
$module_name='visp_vehicle_check';
$subpanel_layout = array (
  'top_buttons' => 
  array (
    0 => 
    array (
      'widget_class' => 'SubPanelTopCreateButton',
    ),
    1 => 
    array (
      'widget_class' => 'SubPanelTopSelectButton',
      'popup_module' => 'visp_vehicle_check',
    ),
  ),
  'where' => '',
  'list_fields' => 
  array (
    'name' => 
    array (
      'type' => 'name',
      'link' => true,
      'width' => '45%',
      'vname' => 'LBL_NAME',
      'default' => true,
      'widget_class' => 'SubPanelDetailViewLink',
      'target_module' => NULL,
      'target_record_key' => NULL,
    ),
    'date_of_check' => 
    array (
      'type' => 'date',
      'vname' => 'LBL_DATE_OF_CHECK',
      'width' => '10%',
      'default' => true,
    ),
    'mileage' => 
    array (
      'type' => 'int',
      'vname' => 'LBL_MILEAGE',
      'width' => '10%',
      'default' => true,
    ),
    'damage_description' => 
    array (
      'type' => 'text',
      'studio' => 'visible',
      'vname' => 'LBL_DAMAGE_DESCRIPTION',
      'sortable' => false,
      'width' => '10%',
      'default' => true,
    ),
    'edit_button' => 
    array (
      'vname' => 'LBL_EDIT_BUTTON',
      'widget_class' => 'SubPanelEditButton',
      'module' => 'visp_vehicle_check',
      'width' => '4%',
      'default' => true,
    ),
    'remove_button' => 
    array (
      'vname' => 'LBL_REMOVE',
      'widget_class' => 'SubPanelRemoveButton',
      'module' => 'visp_vehicle_check',
      'width' => '5%',
      'default' => true,
    ),
  ),
);