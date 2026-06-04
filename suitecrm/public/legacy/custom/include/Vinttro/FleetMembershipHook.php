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

                    // If the foreign key ID field is explicitly populated on the bean, process it
                    if (!empty($id_field) && isset($bean->$id_field) && is_string($bean->$id_field) && !empty($bean->$id_field)) {
                        
                        $target_module = isset($defs['module']) && is_string($defs['module']) ? $defs['module'] : '';

                        // Handle Contacts Relationship
                        if ($target_module === 'Contacts') {
                            $contact = BeanFactory::getBean('Contacts', $bean->$id_field);
                            if ($contact && !empty($contact->id)) {
                                $contact_display = !empty($contact->full_name) ? $contact->full_name : $contact->get_summary_text();
                            }
                        }
                        
                        // Handle Fleet Relationship (Direct/Relate Field path)
                        else if ($target_module === 'visp_fleet' || (empty($target_module) && isset($defs['link']) && is_string($defs['link']) && strpos($defs['link'], 'fleet') !== false)) {
                            $fleet = BeanFactory::getBean('visp_fleet', $bean->$id_field);
                            if ($fleet && !empty($fleet->id)) {
                                $fleet_display = $fleet->get_summary_text();
                            }
                        }
                    }
                }
            }
        }

        // 3. CRITICAL FALLBACK FOR SUBPANEL SAVES
        // If the fleet_display is still unknown after the loop, it means this was created 
        // from a subpanel view where the relationship isn't bound to the fields yet.
        if ($fleet_display === 'Unknown Fleet') {
            $subpanel_parent_id = '';
            
            if (!empty($_REQUEST['relate_id'])) {
                $subpanel_parent_id = $_REQUEST['relate_id'];
            } elseif (!empty($_REQUEST['parent_id'])) {
                $subpanel_parent_id = $_REQUEST['parent_id'];
            }

            // If we successfully caught the parent ID from the background request context, load it!
            if (!empty($subpanel_parent_id) && is_string($subpanel_parent_id)) {
                $fleet = BeanFactory::getBean('visp_fleet', $subpanel_parent_id);
                if ($fleet && !empty($fleet->id)) {
                    $fleet_display = $fleet->get_summary_text();
                }
            }
        }

        // 4. Force overwrite the required 'name' property
        $bean->name = $contact_display . " - " . $fleet_display;
    }
}