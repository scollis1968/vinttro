<?php
 // created: 2026-11-05 07:02:59
$layout_defs["visp_vehicle"]["subpanel_setup"]['visp_vehicle_notes'] = array (
  'order' => 100,
  'module' => 'Notes',
  'subpanel_name' => 'default',
  'sort_order' => 'asc',
  'sort_by' => 'id',
  'title_key' => 'LBL_VISP_VEHICLE_NOTES_FROM_NOTES_TITLE',
  'get_subpanel_data' => 'visp_vehicle_notes',
  'top_buttons' => 
  array (
    0 => 
    array (
      'widget_class' => 'SubPanelTopButtonQuickCreate',
    ),
    1 => 
    array (
      'widget_class' => 'SubPanelTopSelectButton',
      'mode' => 'MultiSelect',
    ),
  ),
);
