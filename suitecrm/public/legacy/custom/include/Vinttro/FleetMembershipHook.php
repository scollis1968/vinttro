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

        // 2. Safely parse field definitions from the record
        if (!empty($bean->field_defs) && is_array($bean->field_defs)) {
            foreach ($bean->field_defs as $field_name => $defs) {
                if (isset($defs['type']) && $defs['type'] == 'relate') {
                    
                    $id_field = isset($defs['id_name']) ? $defs['id_name'] : '';

                    if (!empty($id_field) && isset($bean->$id_field) && is_string($bean->$id_field) && !empty($bean->$id_field)) {
                        
                        $target_module = isset($defs['module']) && is_string($defs['module']) ? $defs['module'] : '';

                        // Handle Contacts Relationship
                        if ($target_module === 'Contacts') {
                            $contact = BeanFactory::getBean('Contacts', $bean->$id_field);
                            if ($contact && !empty($contact->id)) {
                                $contact_display = !empty($contact->full_name) ? $contact->full_name : $contact->get_summary_text();
                            }
                        }
                        
                        // Handle Fleet Relationship (Standard field path)
                        else if ($target_module === 'visp_fleet') {
                            $fleet = BeanFactory::getBean('visp_fleet', $bean->$id_field);
                            if ($fleet && !empty($fleet->id)) {
                                $fleet_display = $fleet->get_summary_text();
                            }
                        }
                    }
                }
            }
        }

        // 3. BULLETPROOF ADVANCED JSON PAYLOAD SCANNER FOR SUBPANELS
        // If the fleet display is still unknown, parse the raw GraphQL JSON payload directly
        if ($fleet_display === 'Unknown Fleet') {
            $raw_payload = file_get_contents('php://input');
            
            if (!empty($raw_payload)) {
                $payload = json_decode($raw_payload, true);
                
                // Collect target variable blocks sent by the Angular frontend
                $blocks_to_scan = [];
                if (!empty($payload['variables']['input'])) {
                    $blocks_to_scan[] = $payload['variables']['input'];
                    if (!empty($payload['variables']['input']['attributes'])) {
                        $blocks_to_scan[] = $payload['variables']['input']['attributes'];
                    }
                }

                // Scan the input packet for any valid 36-character Fleet ID
                foreach ($blocks_to_scan as $target_block) {
                    foreach ($target_block as $key => $value) {
                        if (is_string($value) && strlen($value) === 36) {
                            // Verify if this string is a real ID inside the visp_fleet table
                            $check_fleet = BeanFactory::getBean('visp_fleet', $value);
                            if ($check_fleet && !empty($check_fleet->id)) {
                                $fleet_display = $check_fleet->get_summary_text();
                                break 2; // Success! Break out of both loops
                            }
                        }
                    }
                }
            }
        }

        // 4. Force overwrite the required 'name' property
        $bean->name = $contact_display . " - " . $fleet_display;
    }
}