<?php
// Complete, Studio-compliant custom checkbox definition for Portal Active
$dictionary['Contact']['fields']['portal_active_c'] = array(
    'name' => 'portal_active_c',
    'vname' => 'LBL_PORTAL_ACTIVE',
    'type' => 'bool',
    'dbType' => 'bool',
    'source' => 'custom_fields', // Forces Studio Layouts to recognize the field
    'id_name' => 'Contacts_custom_fields',
    'inline_edit' => '1',
    'labelValue' => 'Portal Active',
    'default' => '0',
    'audited' => false,
    'massupdate' => false,
    'duplicate_merge' => 'disabled',
    'reportable' => true,
    'importable' => 'true',
    'studio' => 'visible',
);