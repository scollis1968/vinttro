<?php
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class AutoFillDriverName 
{
    public function set_name($bean, $event, $arguments) 
    {
        // If name is empty, auto-populate it using the linked Contact details
        if (empty($bean->name)) {
            // Adjust 'contact_relationship_name' to your actual 1:1 relationship link field
            $bean->name = "Driver Info: " . ($bean->contact_name ?? $bean->id);
        }
    }
}