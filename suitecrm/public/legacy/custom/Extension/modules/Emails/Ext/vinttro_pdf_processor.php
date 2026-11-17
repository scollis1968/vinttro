<?php
// Ensure this file is not accessed directly
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$hook_array['after_save'][] = array(
    1,                                   // Processing order
    'Extract PDF and Link to Client',    // Label
    'custom/modules/Emails/VinttroEmailHandler.php', // Path to the logic file
    'VinttroEmailHandler',               // The Class name
    'processEmailAttachments'            // The Function name
);
