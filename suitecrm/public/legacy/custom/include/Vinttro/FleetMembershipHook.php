<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class FleetMembershipHook {
    
    public function generateRecordName($bean, $event, $arguments) {
        // 1. Initialize fallback names
        $contact_display = 'Unknown Contact';
        $fleet_display = 'Unknown Fleet';

        // 2. Identify relationship field names dynamically
        // Studio 1:M relationships pass the selected names in the request payload
        foreach ($bean->field_defs as $field_name => $defs) {
            if ($defs['type'] == 'relate') {
                if ($defs['module'] == 'Contacts' && !empty($bean->$field_name)) {
                    $contact_display = $bean->$field_name;
                }
                if (isset($defs['link']) && strpos($defs['link'], 'fleet') !== false && !empty($bean->$field_name)) {
                    $fleet_display = $bean->$field_name;
                }
            }
        }

        // 3. Force overwrite the required 'name' property
        $bean->name = $contact_display . " - " . $fleet_display;
    }
}