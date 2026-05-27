<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// --- File inclusion logger ---
file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - File was included\n", FILE_APPEND);

class WPSyncHook {
    
    public function syncContactHook($bean, $event, $arguments) {
        $this->executeSyncForContact($bean);
    }

    public function syncVehicleHook($vehicleBean, $event, $arguments) {
        $log = '/tmp/vinttro_ladder_debug.log';
        file_put_contents($log, date('Y-m-d H:i:s') . " - [syncVehicleHook] triggered for Vehicle ID: " . $vehicleBean->id . "\n", FILE_APPEND);
        
        $rel_name = 'visp_vehicle_contacts'; // The relationship link name back to Contacts

        if ($vehicleBean->load_relationship($rel_name)) {
            $relatedContacts = $vehicleBean->$rel_name->getBeans();
            file_put_contents($log, "   - [syncVehicleHook] Found " . count($relatedContacts) . " direct contacts linked to vehicle.\n", FILE_APPEND);

            foreach ($relatedContacts as $contact) {
                $this->executeSyncForContact($contact);
            }
        } else {
            file_put_contents($log, "   - [syncVehicleHook] ❌ Failed loading direct contact relationship '$rel_name'\n", FILE_APPEND);
        }
    }

    /**
     * The single source of truth for gathering data and blasting WP
     */
    public function executeSyncForContact($contactBean) {
        if (empty($contactBean->portal_active_c)) {
            return; // Skip if portal isn't active
        }

        $payload = [
            'email'             => $contactBean->email1,
            'first_name'        => $contactBean->first_name,
            'last_name'         => $contactBean->last_name,
            'membership_status' => $contactBean->membership_status_c,
            'vehicles'          => $this->getIndividualGarage($contactBean),
            'fleets'            => $this->getRelatedFleets($contactBean)
        ];

        $this->callWPAPI($payload);
    }

    public function syncToWordpress($bean, $event, $arguments) {
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 1 trigered\n", FILE_APPEND);
        
        if (empty($bean->portal_active_c)) {
            return;
        }

        $payload = [
            'email'             => $bean->email1,
            'first_name'        => $bean->first_name,
            'last_name'         => $bean->last_name,
            'membership_status' => $bean->membership_status_c,
            'vehicles'          => [] 
        ];

        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 2 payload = " . json_encode($payload) . "\n" , FILE_APPEND);
        $rel_name = 'visp_vehicle_contacts'; 
        
        if ($bean->load_relationship($rel_name)) {
            $relatedVehicles = $bean->$rel_name->getBeans();

            foreach ($relatedVehicles as $vehicle) {
                $payload['vehicles'][] = [
                    'make'         => $vehicle->make,
                    'model'        => $vehicle->model,
                    'reg'          => $vehicle->name,
                    'mot_expiry'   => $vehicle->date_mot,
                    'next_service' => $vehicle->date_service,
                    'ins_expiry'   => $vehicle->date_registered,
                    'image_url'    => $this->getVehicleImageUrl($vehicle)
                ];
            }
        }

        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 3 payload = " . print_r($payload, true) . "\n" , FILE_APPEND);
        $this->callWPAPI($payload);
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 4 All done! \n" , FILE_APPEND);
    }

    /**
     * Triggered automatically whenever a Vehicle record is saved
     */
    public function syncVehicleFleetUpdate($vehicleBean, $event, $arguments) {
        $log = '/tmp/vinttro_ladder_debug.log';
        file_put_contents($log, "==================================================================\n", FILE_APPEND);
        file_put_contents($log, date('Y-m-d H:i:s') . " - [STEP 1] Hook triggered on Vehicle. ID: " . $vehicleBean->id . " | Reg: " . $vehicleBean->name . "\n", FILE_APPEND);

        // 1. Name of relationship between Vehicles and Fleets
        $rel_vehicle_fleet = 'visp_fleet_visp_vehicle'; 
        file_put_contents($log, " - Loading Vehicle -> Fleet relationship: '$rel_vehicle_fleet'\n", FILE_APPEND);

        if ($vehicleBean->load_relationship($rel_vehicle_fleet)) {
            $relatedFleets = $vehicleBean->$rel_vehicle_fleet->getBeans();
            file_put_contents($log, "   🟢 SUCCESS: Found " . count($relatedFleets) . " linked fleet(s).\n", FILE_APPEND);
            
            foreach ($relatedFleets as $fleet) {
                file_put_contents($log, "   -> Processing Fleet: ID: " . $fleet->id . " | Name: " . $fleet->name . "\n", FILE_APPEND);
                
                // Compile data structure
                $fleetPayload = $this->compileFleetDataStructure($fleet);
                
                // Route update out to admins
                $this->distributeToFleetAdmins($fleet, $fleetPayload);
            }
        } else {
            file_put_contents($log, "   ❌ FAILED: Could not load link '$rel_vehicle_fleet' on Vehicle bean.\n", FILE_APPEND);
        }
    }

    /**
     * Compiles the full nested dataset for a specific Fleet
     */
    private function compileFleetDataStructure($fleetBean) {
        $log = '/tmp/vinttro_ladder_debug.log';
        file_put_contents($log, " - [STEP 2] Compiling data structure for Fleet: " . $fleetBean->name . "\n", FILE_APPEND);

        $fleetStructure = [
            'name'     => $fleetBean->name,
            'vehicles' => []
        ];

        // Name of relationship between Fleets and Vehicles
        $rel_fleet_vehicles = 'visp_fleet_visp_vehicle';
        file_put_contents($log, "   - Loading Fleet -> Vehicles relationship: '$rel_fleet_vehicles'\n", FILE_APPEND);
        
        if ($fleetBean->load_relationship($rel_fleet_vehicles)) {
            $vehicles = $fleetBean->$rel_fleet_vehicles->getBeans();
            file_put_contents($log, "     🟢 SUCCESS: Found " . count($vehicles) . " vehicle(s) inside this fleet.\n", FILE_APPEND);
            
            foreach ($vehicles as $vehicle) {
                $fleetStructure['vehicles'][] = [
                    'make'                   => $vehicle->make,
                    'model'                  => $vehicle->model,
                    'reg'                    => $vehicle->name,
                    'date_next_mot'          => $vehicle->date_next_mot,
                    'date_last_service'      => $vehicle->date_last_service,
                    'date_next_service'      => $vehicle->date_next_service,
                    'date_last_check'        => $vehicle->date_last_check,
                    'mot_expiry'             => $vehicle->date_mot,
                    'ins_expiry'             => $vehicle->date_registered,
                    'outstanding_issues'     => $this->getVehicleIssues($vehicle)
                ];
            }
        } else {
            file_put_contents($log, "     ❌ FAILED: Could not load link '$rel_fleet_vehicles' on Fleet bean.\n", FILE_APPEND);
        }

        return $fleetStructure;
    }

    /**
     * Finds all fleet administrators and broadcasts the data payload to WordPress
     */
    private function distributeToFleetAdmins($fleetBean, $fleetPayload) {
        $log = '/tmp/vinttro_ladder_debug.log';
        file_put_contents($log, " - [STEP 3] Distributing updates for Fleet: " . $fleetBean->name . "\n", FILE_APPEND);

        // Name of relationship between Fleets and your new Membership module
        $rel_fleet_memberships = 'visp_fleet_visp_fleet_membership';
        file_put_contents($log, "   - Loading Fleet -> Membership relationship: '$rel_fleet_memberships'\n", FILE_APPEND);

        if ($fleetBean->load_relationship($rel_fleet_memberships)) {
            $memberships = $fleetBean->$rel_fleet_memberships->getBeans();
            file_put_contents($log, "     🟢 SUCCESS: Found " . count($memberships) . " total personnel records.\n", FILE_APPEND);

            foreach ($memberships as $membership) {
                file_put_contents($log, "     -> Checking Personnel ID: " . $membership->id . " | label: " . $membership->name . "\n", FILE_APPEND);
                file_put_contents($log, "        Raw value of is_fleetadmin field: '" . print_r($membership->is_fleetadmin, true) . "'\n", FILE_APPEND);

                // Check if the "is_fleetadmin" checkbox is checked
                if (!empty($membership->is_fleetadmin) && ($membership->is_fleetadmin == 1 || $membership->is_fleetadmin === true || $membership->is_fleetadmin == '1')) {
                    file_put_contents($log, "        ⭐ MATCH: Record is a Fleet Admin. Fetching Contact record...\n", FILE_APPEND);
                    
                    // Name of relationship between Membership module and Contacts
                    $rel_membership_contact = 'contacts_visp_fleet_membership';
                    file_put_contents($log, "        - Loading Membership -> Contact relationship: '$rel_membership_contact'\n", FILE_APPEND);
                    
                    if ($membership->load_relationship($rel_membership_contact)) {
                        $contacts = $membership->$rel_membership_contact->getBeans();
                        $contact = reset($contacts); 

                        if ($contact && !empty($contact->email1)) {
                            file_put_contents($log, "          🚀 DISPATCHING API: Targeting Admin Email: " . $contact->email1 . "\n", FILE_APPEND);
                            
                            $wpPayload = [
                                'email'             => $contact->email1,
                                'first_name'        => $contact->first_name,
                                'last_name'         => $contact->last_name,
                                'membership_status' => $contact->membership_status_c ?? 'Active',
                                'fleets'            => [$fleetPayload]
                            ];

                            $this->callWPAPI($wpPayload);
                        } else {
                            file_put_contents($log, "          ⚠️ WARNING: Contact found but Email field (email1) is blank.\n", FILE_APPEND);
                        }
                    } else {
                        file_put_contents($log, "          ❌ FAILED: Could not load link '$rel_membership_contact' on Membership bean.\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents($log, "        skipping: User is not marked as a Fleet Admin.\n", FILE_APPEND);
                }
            }
        } else {
            file_put_contents($log, "     ❌ FAILED: Could not load link '$rel_fleet_memberships' on Fleet bean.\n", FILE_APPEND);
        }
    }

    /**
     * Helper to compile outstanding issues for a single vehicle
     */
    private function getVehicleIssues($vehicleBean) {
        $log = '/tmp/vinttro_ladder_debug.log';
        $issuesData = [];
        
        // Name of relationship between Vehicles and Issues module
        $rel_vehicle_issues = 'visp_vehicle_visp_vehicle_issues'; 
        
        if ($vehicleBean->load_relationship($rel_vehicle_issues)) {
            $issues = $vehicleBean->$rel_vehicle_issues->getBeans();
            
            foreach ($issues as $issue) {
                $issuesData[] = [
                    'title'               => $issue->name,
                    'description'         => $issue->description,
                    'issue_severity'      => $issue->severity_c, 
                    'date_issue_reported' => $issue->date_entered
                ];
            }
        } else {
            file_put_contents($log, "     ⚠️ Trace Note: Relationship '$rel_vehicle_issues' not loaded for Vehicle: " . $vehicleBean->name . "\n", FILE_APPEND);
        }
        return $issuesData;
    }

    private function getVehicleImageUrl($vehicle) {
        if (!empty($vehicle->photo_c)) {
            return "https://uatcrm.vinttro.co.uk/upload/" . $vehicle->photo_c;
        }
        return "https://vinttro.co.uk/wp-content/uploads/placeholder-car.png";
    }

    private function callWPAPI($payload) {
        $url = "https://uat.vinttro.co.uk/wp-json/vinttro/v1/create-member/";

        $username = 'vinttro_admin';
        $app_password = '6YSN vLuS Pa4d kMIk flMY TYoQ';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$app_password")
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if(curl_errno($ch)) {
            $error_msg = curl_error($ch);
            file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - CURL ERROR: $error_msg\n", FILE_APPEND);
        }

        curl_close($ch);
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - WP Response Code: $http_code | Response: $response\n", FILE_APPEND);
    }
}