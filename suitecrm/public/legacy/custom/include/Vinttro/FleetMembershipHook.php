<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class FleetMembershipHook 
{
    public function generateRecordName($bean, $event, $arguments) {
        // 1. Initialize fallback names
        $contact_display = 'Unknown Contact';
        $fleet_display = 'Unknown Fleet';

        // 2. Safely parse field definitions
        if (!empty($bean->field_defs) && is_array($bean->field_defs)) {
            foreach ($bean->field_defs as $field_name => $defs) {
                if (isset($defs['type']) && $defs['type'] == 'relate') {
                    
                    // Relate fields use an 'id_name' property to store the actual DB foreign key
                    $id_field = isset($defs['id_name']) ? $defs['id_name'] : '';

                    // DEFENSIVE GUARD: Ensure the foreign key ID exists and is a valid scalar string
                    if (!empty($id_field) && isset($bean->$id_field) && is_string($bean->$id_field) && !empty($bean->$id_field)) {
                        
                        // TYPE GUARD: Safely extract and verify the module definition is a valid string
                        $target_module = isset($defs['module']) && is_string($defs['module']) ? $defs['module'] : '';

                        // Handle Contacts Relationship
                        if ($target_module === 'Contacts') {
                            $contact = BeanFactory::getBean('Contacts', $bean->$id_field);
                            if ($contact && !empty($contact->id)) {
                                $contact_display = !empty($contact->full_name) ? $contact->full_name : $contact->get_summary_text();
                            }
                        }
                        
                        // Handle Fleet Relationship (Strictly matched to visp_fleet)
                        if ($target_module === 'visp_fleet' || (!empty($target_module) && isset($defs['link']) && is_string($defs['link']) && strpos($defs['link'], 'fleet') !== false)) {
                            $fleet = BeanFactory::getBean($target_module, $bean->$id_field);
                            if ($fleet && !empty($fleet->id)) {
                                $fleet_display = $fleet->get_summary_text();
                            }
                        }
                    }
                }
            }
        }

        // 3. Force overwrite the required 'name' property
        $bean->name = $contact_display . " - " . $fleet_display;
    }
}