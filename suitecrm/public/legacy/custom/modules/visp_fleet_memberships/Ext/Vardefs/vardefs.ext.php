<?php 
 //WARNING: The contents of this file are auto-generated


// created: 2026-11-05 07:03:00
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_visp_fleet_memberships"] = array (
  'name' => 'visp_fleet_visp_fleet_memberships',
  'type' => 'link',
  'relationship' => 'visp_fleet_visp_fleet_memberships',
  'source' => 'non-db',
  'module' => 'visp_fleet',
  'bean_name' => 'visp_fleet',
  'vname' => 'LBL_VISP_FLEET_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_TITLE',
  'id_name' => 'visp_fleet_visp_fleet_membershipsvisp_fleet_ida',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_visp_fleet_memberships_name"] = array (
  'name' => 'visp_fleet_visp_fleet_memberships_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_FLEET_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_TITLE',
  'save' => true,
  'id_name' => 'visp_fleet_visp_fleet_membershipsvisp_fleet_ida',
  'link' => 'visp_fleet_visp_fleet_memberships',
  'table' => 'visp_fleet',
  'module' => 'visp_fleet',
  'rname' => 'name',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_visp_fleet_membershipsvisp_fleet_ida"] = array (
  'name' => 'visp_fleet_visp_fleet_membershipsvisp_fleet_ida',
  'type' => 'link',
  'relationship' => 'visp_fleet_visp_fleet_memberships',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_FLEET_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
);


// created: 2026-11-05 07:02:59
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_memberships_contacts"] = array (
  'name' => 'visp_fleet_memberships_contacts',
  'type' => 'link',
  'relationship' => 'visp_fleet_memberships_contacts',
  'source' => 'non-db',
  'module' => 'Contacts',
  'bean_name' => 'Contact',
  'vname' => 'LBL_VISP_FLEET_MEMBERSHIPS_CONTACTS_FROM_CONTACTS_TITLE',
  'id_name' => 'visp_fleet_memberships_contactscontacts_ida',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_memberships_contacts_name"] = array (
  'name' => 'visp_fleet_memberships_contacts_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_FLEET_MEMBERSHIPS_CONTACTS_FROM_CONTACTS_TITLE',
  'save' => true,
  'id_name' => 'visp_fleet_memberships_contactscontacts_ida',
  'link' => 'visp_fleet_memberships_contacts',
  'table' => 'contacts',
  'module' => 'Contacts',
  'rname' => 'name',
  'db_concat_fields' => 
  array (
    0 => 'first_name',
    1 => 'last_name',
  ),
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_fleet_memberships_contactscontacts_ida"] = array (
  'name' => 'visp_fleet_memberships_contactscontacts_ida',
  'type' => 'link',
  'relationship' => 'visp_fleet_memberships_contacts',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_FLEET_MEMBERSHIPS_CONTACTS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
);


// created: 2026-11-05 07:03:00
$dictionary["visp_fleet_memberships"]["fields"]["visp_driver_visp_fleet_memberships"] = array (
  'name' => 'visp_driver_visp_fleet_memberships',
  'type' => 'link',
  'relationship' => 'visp_driver_visp_fleet_memberships',
  'source' => 'non-db',
  'module' => 'visp_driver',
  'bean_name' => 'visp_driver',
  'vname' => 'LBL_VISP_DRIVER_VISP_FLEET_MEMBERSHIPS_FROM_VISP_DRIVER_TITLE',
  'id_name' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_driver_visp_fleet_memberships_name"] = array (
  'name' => 'visp_driver_visp_fleet_memberships_name',
  'type' => 'relate',
  'source' => 'non-db',
  'vname' => 'LBL_VISP_DRIVER_VISP_FLEET_MEMBERSHIPS_FROM_VISP_DRIVER_TITLE',
  'save' => true,
  'id_name' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
  'link' => 'visp_driver_visp_fleet_memberships',
  'table' => 'visp_driver',
  'module' => 'visp_driver',
  'rname' => 'name',
);
$dictionary["visp_fleet_memberships"]["fields"]["visp_driver_visp_fleet_membershipsvisp_driver_ida"] = array (
  'name' => 'visp_driver_visp_fleet_membershipsvisp_driver_ida',
  'type' => 'link',
  'relationship' => 'visp_driver_visp_fleet_memberships',
  'source' => 'non-db',
  'reportable' => false,
  'side' => 'right',
  'vname' => 'LBL_VISP_DRIVER_VISP_FLEET_MEMBERSHIPS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
);

?>