<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class FleetMembershipHook {
    
    public function generateRecordName($bean, $event, $arguments) {
        // 1. Initialize fallback names
        $contact_display = 'Unknown Contact';
        $fleet_display = 'Unknown Fleet';

        // 2. Identify relationship field names dynamically
        foreach ($bean->field_defs as $field_name => $defs) {
            if (isset($defs['type']) && $defs['type'] == 'relate') {
                
                // Relate fields use an 'id_name' property to store the actual DB foreign key
                $id_field = isset($defs['id_name']) ? $defs['id_name'] : '';

                if (!empty($id_field) && !empty($bean->$id_field)) {
                    
                    // Handle Contacts Relationship
                    if ($defs['module'] == 'Contacts') {
                        $contact = BeanFactory::getBean('Contacts', $bean->$id_field);
                        if ($contact && !empty($contact->id)) {
                            // Contacts use full_name property safely
                            $contact_display = !empty($contact->full_name) ? $contact->full_name : $contact->get_summary_text();
                        }
                    }
                    
                    // Handle Fleet Relationship
                    if ($defs['module'] == 'visp_fleet' || (isset($defs['link']) && strpos($defs['link'], 'fleet') !== false)) {
                        $fleet = BeanFactory::getBean($defs['module'], $bean->$id_field);
                        if ($fleet && !empty($fleet->id)) {
                            $fleet_display = $fleet->get_summary_text();
                        }
                    }
                }
            }
        }

        // 3. Force overwrite the required 'name' property
        $bean->name = $contact_display . " - " . $fleet_display;
    }
}