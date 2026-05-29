<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

// Triggers after the Contact is saved to handle the Driver record creation
$hook_array['after_save'][] = array(
    10,
    'Auto Create and Link Driver Record',
    'custom/modules/Contacts/ContactsDriverHook.php',
    'ContactsDriverHook',
    'handleDriverRelation'
);