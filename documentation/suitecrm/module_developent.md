# SuiteCRM Module development guide.

The custom modules and database definitions are part fo the legacy system


```
cd /var/www/suitecrm/public/legacy/custom/modules

```

## Subpanels - (related modules)

The related modules shown below a main module are displayed using a subpanel. These subpanels can be edited using the studio feature, however it appears that they are vaporised when a module is redeployed.

For example :-
The fleet module has a one to many relationship with vehicles the subpanel that show the vehicles in the fleet ca be found in

/var/www/suitecrm/public/legacy/custom/modules/vips_Vehicle/metadata/subpanels

```
cat /var/www/suitecrm/public/legacy/custom/modules/vips_Vehicle/metadata/subpanels/vips_fleet_subpanel_vips_fleet_vips_vehicle.php
<?php
// created: 2026-03-21 11:07:50
$subpanel_layout['list_fields'] = array (
  'registration_number' => 
  array (
    'type' => 'varchar',
    'vname' => 'LBL_REGISTRATION_NUMBER',
    'width' => '10%',
    'default' => true,
  ),
  'main_driver' => 
  array (
    'type' => 'relate',
    'studio' => 'visible',
    'vname' => 'LBL_MAIN_DRIVER',
    'id' => 'CONTACT_ID_C',
    'link' => true,
    'width' => '10%',
    'default' => true,
    'widget_class' => 'SubPanelDetailViewLink',
    'target_module' => 'Contacts',
    'target_record_key' => 'contact_id_c',
  ),
  'date_service' => 
  array (
    'type' => 'date',
    'vname' => 'LBL_DATE_SERVICE',
    'width' => '10%',
    'default' => true,
  ),
  'date_mot' => 
  array (
    'type' => 'date',
    'vname' => 'LBL_DATE_MOT',
    'width' => '10%',
    'default' => true,
  ),
  'edit_button' => 
  array (
    'vname' => 'LBL_EDIT_BUTTON',
    'widget_class' => 'SubPanelEditButton',
    'module' => 'vips_Vehicle',
    'width' => '4%',
    'default' => true,
  ),
  'remove_button' => 
  array (
    'vname' => 'LBL_REMOVE',
    'widget_class' => 'SubPanelRemoveButton',
    'module' => 'vips_Vehicle',
    'width' => '5%',
    'default' => true,
  ),
);
```