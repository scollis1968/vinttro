<?php
// created: 2026-11-05 07:03:00
$dictionary["Contact"]["fields"]["visp_driver_contacts"] = array (
  'name' => 'visp_driver_contacts',
  'type' => 'link',
  'relationship' => 'visp_driver_contacts',
  'source' => 'non-db',
  'module' => 'visp_driver',
  'bean_name' => 'visp_driver',
  'vname' => 'LBL_VISP_DRIVER_CONTACTS_FROM_VISP_DRIVER_TITLE',
  'id_name' => 'visp_driver_contactsvisp_driver_ida',
);
$dictionary["Contact"]["fields"]["visp_driver_contacts_name"] = array (
  'name' => 'visp_driver_contacts_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_DRIVER_CONTACTS_FROM_VISP_DRIVER_TITLE',
  'save' => true,
  'id_name' => 'visp_driver_contactsvisp_driver_ida',
  'link' => 'visp_driver_contacts',
  'table' => 'visp_driver',
  'module' => 'visp_driver',
  'rname' => 'name',
);
$dictionary["Contact"]["fields"]["visp_driver_contactsvisp_driver_ida"] = array (
  'name' => 'visp_driver_contactsvisp_driver_ida',
  'type' => 'link',
  'relationship' => 'visp_driver_contacts',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'left',
  'vname' => 'LBL_VISP_DRIVER_CONTACTS_FROM_VISP_DRIVER_TITLE',
);
