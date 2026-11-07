<?php 
 //WARNING: The contents of this file are auto-generated


// created: 2026-11-05 07:03:00
$dictionary["visp_driver"]["fields"]["visp_driver_visp_fleet_memberships"] = array (
  'name' => 'visp_driver_visp_fleet_memberships',
  'type' => 'link',
  'relationship' => 'visp_driver_visp_fleet_memberships',
  'source' => 'non-db',
  'module' => 'visp_fleet_memberships',
  'bean_name' => 'visp_fleet_memberships',
  'side' => 'right',
  'vname' => 'LBL_VISP_DRIVER_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
);


// created: 2026-11-05 07:03:00
$dictionary["visp_driver"]["fields"]["visp_driver_contacts"] = array (
  'name' => 'visp_driver_contacts',
  'type' => 'link',
  'relationship' => 'visp_driver_contacts',
  'source' => 'non-db',
  'module' => 'Contacts',
  'bean_name' => 'Contact',
  'vname' => 'LBL_VISP_DRIVER_CONTACTS_FROM_CONTACTS_TITLE',
  'id_name' => 'visp_driver_contactscontacts_idb',
);
$dictionary["visp_driver"]["fields"]["visp_driver_contacts_name"] = array (
  'name' => 'visp_driver_contacts_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_DRIVER_CONTACTS_FROM_CONTACTS_TITLE',
  'save' => true,
  'id_name' => 'visp_driver_contactscontacts_idb',
  'link' => 'visp_driver_contacts',
  'table' => 'contacts',
  'module' => 'Contacts',
  'rname' => 'name',
  'db_concat_fields' => 
  array (
    0 => 'first_name',
    1 => 'last_name',
  ),
);
$dictionary["visp_driver"]["fields"]["visp_driver_contactscontacts_idb"] = array (
  'name' => 'visp_driver_contactscontacts_idb',
  'type' => 'link',
  'relationship' => 'visp_driver_contacts',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'left',
  'vname' => 'LBL_VISP_DRIVER_CONTACTS_FROM_CONTACTS_TITLE',
);

?>