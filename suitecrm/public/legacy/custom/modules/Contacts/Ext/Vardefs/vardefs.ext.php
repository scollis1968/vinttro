<?php 
 //WARNING: The contents of this file are auto-generated


// created: 2026-11-05 07:02:59
$dictionary["Contact"]["fields"]["visp_fleet_memberships_contacts"] = array (
  'name' => 'visp_fleet_memberships_contacts',
  'type' => 'link',
  'relationship' => 'visp_fleet_memberships_contacts',
  'source' => 'non-db',
  'module' => 'visp_fleet_memberships',
  'bean_name' => 'visp_fleet_memberships',
  'side' => 'right',
  'vname' => 'LBL_VISP_FLEET_MEMBERSHIPS_CONTACTS_FROM_VISP_FLEET_MEMBERSHIPS_TITLE',
);


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


 // created: 2025-10-08 13:30:47
$dictionary['Contact']['fields']['jjwg_maps_lat_c']['inline_edit']=1;

 

 // created: 2025-10-08 13:30:47
$dictionary['Contact']['fields']['jjwg_maps_lng_c']['inline_edit']=1;

 

 // created: 2026-06-04 09:42:34
$dictionary['Contact']['fields']['portal_active_c']['inline_edit']='1';
$dictionary['Contact']['fields']['portal_active_c']['labelValue']='VIsP Portal Active';

 

 // created: 2025-10-08 13:30:47
$dictionary['Contact']['fields']['jjwg_maps_address_c']['inline_edit']=1;

 

 // created: 2026-05-28 22:27:23
$dictionary['Contact']['fields']['is_driver_c']['inline_edit']='1';
$dictionary['Contact']['fields']['is_driver_c']['labelValue']='is driver';

 

 // created: 2026-01-24 15:30:41
$dictionary['Contact']['fields']['photo']['name']='photo';
$dictionary['Contact']['fields']['photo']['inline_edit']=true;
$dictionary['Contact']['fields']['photo']['help']='Photo help text';
$dictionary['Contact']['fields']['photo']['merge_filter']='disabled';
$dictionary['Contact']['fields']['photo']['unified_search']=true;
$dictionary['Contact']['fields']['photo']['border']='1';
$dictionary['Contact']['fields']['photo']['width']='160';
$dictionary['Contact']['fields']['photo']['height']='160';

 

 // created: 2025-10-08 13:30:47
$dictionary['Contact']['fields']['jjwg_maps_geocode_status_c']['inline_edit']=1;

 
?>