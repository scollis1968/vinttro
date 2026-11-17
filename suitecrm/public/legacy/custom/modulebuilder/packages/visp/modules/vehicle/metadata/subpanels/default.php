<?php
$module_name='visp_vehicle';
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
      'popup_module' => 'visp_vehicle',
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
    'date_next_service' => 
    array (
      'type' => 'date',
      'vname' => 'LBL_DATE_NEXT_SERVICE',
      'width' => '10%',
      'default' => true,
    ),
    'date_next_mot' => 
    array (
      'type' => 'date',
      'vname' => 'LBL_DATE_NEXT_MOT',
      'width' => '10%',
      'default' => true,
    ),
    'date_next_tax' => 
    array (
      'type' => 'datetimecombo',
      'vname' => 'LBL_DATE_NEXT_TAX',
      'width' => '10%',
      'default' => true,
    ),
    'edit_button' => 
    array (
      'vname' => 'LBL_EDIT_BUTTON',
      'widget_class' => 'SubPanelEditButton',
      'module' => 'visp_vehicle',
      'width' => '4%',
      'default' => true,
    ),
    'remove_button' => 
    array (
      'vname' => 'LBL_REMOVE',
      'widget_class' => 'SubPanelRemoveButton',
      'module' => 'visp_vehicle',
      'width' => '5%',
      'default' => true,
    ),
  ),
);