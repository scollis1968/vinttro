<?php
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
