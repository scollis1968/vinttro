<?php
 // created: 2026-11-05 07:02:59
$layout_defs["visp_vehicle_check"]["subpanel_setup"]['visp_vehicle_check_visp_vehicle_issue'] = array (
  'order' => 100,
  'module' => 'visp_vehicle_issue',
  'subpanel_name' => 'default',
  'sort_order' => 'asc',
  'sort_by' => 'id',
  'title_key' => 'LBL_VISP_VEHICLE_CHECK_VISP_VEHICLE_ISSUE_FROM_VISP_VEHICLE_ISSUE_TITLE',
  'get_subpanel_data' => 'visp_vehicle_check_visp_vehicle_issue',
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
