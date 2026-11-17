<?php
// created: 2026-11-05 07:02:59
$dictionary["visp_vehicle_issue"]["fields"]["visp_vehicle_check_visp_vehicle_issue"] = array (
  'name' => 'visp_vehicle_check_visp_vehicle_issue',
  'type' => 'link',
  'relationship' => 'visp_vehicle_check_visp_vehicle_issue',
  'source' => 'non-db',
  'module' => 'visp_vehicle_check',
  'bean_name' => 'visp_vehicle_check',
  'vname' => 'LBL_VISP_VEHICLE_CHECK_VISP_VEHICLE_ISSUE_FROM_VISP_VEHICLE_CHECK_TITLE',
  'id_name' => 'visp_vehicle_check_visp_vehicle_issuevisp_vehicle_check_ida',
);
$dictionary["visp_vehicle_issue"]["fields"]["visp_vehicle_check_visp_vehicle_issue_name"] = array (
  'name' => 'visp_vehicle_check_visp_vehicle_issue_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_VEHICLE_CHECK_VISP_VEHICLE_ISSUE_FROM_VISP_VEHICLE_CHECK_TITLE',
  'save' => true,
  'id_name' => 'visp_vehicle_check_visp_vehicle_issuevisp_vehicle_check_ida',
  'link' => 'visp_vehicle_check_visp_vehicle_issue',
  'table' => 'visp_vehicle_check',
  'module' => 'visp_vehicle_check',
  'rname' => 'name',
);
$dictionary["visp_vehicle_issue"]["fields"]["visp_vehicle_check_visp_vehicle_issuevisp_vehicle_check_ida"] = array (
  'name' => 'visp_vehicle_check_visp_vehicle_issuevisp_vehicle_check_ida',
  'type' => 'link',
  'relationship' => 'visp_vehicle_check_visp_vehicle_issue',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_VEHICLE_CHECK_VISP_VEHICLE_ISSUE_FROM_VISP_VEHICLE_ISSUE_TITLE',
);
