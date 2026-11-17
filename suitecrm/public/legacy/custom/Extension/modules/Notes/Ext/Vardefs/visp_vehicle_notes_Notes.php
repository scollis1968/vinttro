<?php
// created: 2026-11-05 07:02:59
$dictionary["Note"]["fields"]["visp_vehicle_notes"] = array (
  'name' => 'visp_vehicle_notes',
  'type' => 'link',
  'relationship' => 'visp_vehicle_notes',
  'source' => 'non-db',
  'module' => 'visp_vehicle',
  'bean_name' => 'visp_vehicle',
  'vname' => 'LBL_VISP_VEHICLE_NOTES_FROM_VISP_VEHICLE_TITLE',
  'id_name' => 'visp_vehicle_notesvisp_vehicle_ida',
);
$dictionary["Note"]["fields"]["visp_vehicle_notes_name"] = array (
  'name' => 'visp_vehicle_notes_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_VEHICLE_NOTES_FROM_VISP_VEHICLE_TITLE',
  'save' => true,
  'id_name' => 'visp_vehicle_notesvisp_vehicle_ida',
  'link' => 'visp_vehicle_notes',
  'table' => 'visp_vehicle',
  'module' => 'visp_vehicle',
  'rname' => 'name',
);
$dictionary["Note"]["fields"]["visp_vehicle_notesvisp_vehicle_ida"] = array (
  'name' => 'visp_vehicle_notesvisp_vehicle_ida',
  'type' => 'link',
  'relationship' => 'visp_vehicle_notes',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_VEHICLE_NOTES_FROM_NOTES_TITLE',
);
