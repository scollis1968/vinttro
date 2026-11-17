<?php
 // created: 2026-11-05 07:02:59
$layout_defs["visp_fleet"]["subpanel_setup"]['visp_fleet_visp_fleet_memberships'] = array (
  'order' => 100,
  'module' => 'visp_fleet_memberships',
  'subpanel_name' => 'default',
  'sort_order' => 'asc',
  'sort_by' => 'id',
  'title_key' => 'LBL_VISP_FLEET_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
  'get_subpanel_data' => 'visp_fleet_visp_fleet_memberships',
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
