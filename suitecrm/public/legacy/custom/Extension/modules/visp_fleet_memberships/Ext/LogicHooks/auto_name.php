<?php
$hook_array['before_save'][] = array(
    1,
    'Auto Populate Membership Name',
    'custom/include/Vinttro/FleetMembershipHook.php',
    'FleetMembershipHook',
    'generateRecordName'
);