<?php
// A complete, Studio-compliant custom checkbox definition
$dictionary['Contact']['fields']['is_driver_c'] = array(
    'name' => 'is_driver_c',
    'vname' => 'LBL_IS_DRIVER',
    'type' => 'bool',
    'dbType' => 'bool',
    'source' => 'custom_fields', // CRITICAL: Tells Studio this is a custom field
    'id_name' => 'Contacts_custom_fields',
    'inline_edit' => '1',
    'labelValue' => 'is driver',
    'default' => '0',
    'audited' => false,
    'massupdate' => false,
    'duplicate_merge' => 'disabled',
    'reportable' => true,
    'importable' => 'true',
    'studio' => 'visible',
);