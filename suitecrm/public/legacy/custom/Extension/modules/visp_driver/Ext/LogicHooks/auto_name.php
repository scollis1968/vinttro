<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Connects your existing AutoFillDriverName class to the before_save event
 */
$hook_array['before_save'][] = array(
    1,                                                         // Processing order index
    'Auto Populate Driver Name Field',                         // Description
    'custom/modules/visp_driver/views/before_save_name.php',   // Relative path to your file
    'AutoFillDriverName',                                      // Class Name
    'set_name'                                                 // Method Name
);