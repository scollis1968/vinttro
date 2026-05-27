<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// --- ADD THIS LINE AT THE TOP ---
file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - File was included\n", FILE_APPEND);

class WPSyncHook {
    public function syncContactHook($bean, $event, $arguments) {
        $this->executeSyncForContact($bean);
    }

    public function syncVehicleHook($vehicleBean, $event, $arguments) {
        $rel_name = 'visp_vehicle_contacts'; // The relationship link name back to Contacts

        if ($vehicleBean->load_relationship($rel_name)) {
            $relatedContacts = $vehicleBean->$rel_name->getBeans();

            foreach ($relatedContacts as $contact) {
                // Trigger the centralized sync for every contact linked to this vehicle!
                $this->executeSyncForContact($contact);
            }
        }
    }

    /**
     * The single source of truth for gathering data and blasting WP
     */
    public function executeSyncForContact($contactBean) {
        if (empty($contactBean->portal_active_c)) {
            return; // Skip if portal isn't active
        }

        // Build your massive multi-dimensional payload exactly as you designed it
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
        // --- ADD THIS LINE AT THE TOP ---
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 1 trigered\n", FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
        
        // Make sure 'portal_active_c' matches the field name in Studio
        if (empty($bean->portal_active_c)) {
            return;
        }

        // 2. Prepare the base member data
        $payload = [
            'email'             => $bean->email1,
            'first_name'        => $bean->first_name,
            'last_name'         => $bean->last_name,
            'membership_status' => $bean->membership_status_c, // Adjusted field name
            'vehicles'          => [] 
        ];

        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 2 payload = " . json_encode($payload) . "\n" , FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
        // 3. Load the Many-to-Many Relationship
        // Use the relationship name you found in Studio
        $rel_name = 'visp_vehicle_contacts'; 
        
        if ($bean->load_relationship($rel_name)) {
            // Fetch all related vehicle beans
            $relatedVehicles = $bean->$rel_name->getBeans();

            foreach ($relatedVehicles as $vehicle) {
                $payload['vehicles'][] = [
                    'make'         => $vehicle->make,      // Custom fields usually end in _c
                    'model'        => $vehicle->model,
                    'reg'          => $vehicle->name,         // Assuming 'name' field holds the registration number
                    'mot_expiry'   => $vehicle->date_mot,
                    'next_service' => $vehicle->date_service,
                    'ins_expiry'   => $vehicle->date_registered,
                    'image_url'    => $this->getVehicleImageUrl($vehicle)
                ];
            }
        }

        // 4. Send the payload to WordPress
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 3 payload = " . print_r($payload, true) . "\n" , FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
        $this->callWPAPI($payload);
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 4 All done! \n" , FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
    }

    /**
     * Triggered automatically whenever a Vehicle record is saved
     */
    public function syncVehicleFleetUpdate($vehicleBean, $event, $arguments) {
        // 1. Name of relationship between Vehicles and Fleets
        $rel_vehicle_fleet = 'visp_fleets_visp_vehicles'; 

        if ($vehicleBean->load_relationship($rel_vehicle_fleet)) {
            $relatedFleets = $vehicleBean->$rel_vehicle_fleet->getBeans();
            
            foreach ($relatedFleets as $fleet) {
                // Compile the fleet payload once so we don't repeat DB queries
                $fleetPayload = $this->compileFleetDataStructure($fleet);
                
                // Blast this update out to all qualifying admins linked to this fleet
                $this->distributeToFleetAdmins($fleet, $fleetPayload);
            }
        }
    }

    /**
     * Compiles the full nested dataset for a specific Fleet
     */
    private function compileFleetDataStructure($fleetBean) {
        $fleetStructure = [
            'name'     => $fleetBean->name,
            'vehicles' => []
        ];

        // Name of relationship between Fleets and Vehicles
        $rel_fleet_vehicles = 'visp_fleet_visp_vehicle';
        
        if ($fleetBean->load_relationship($rel_fleet_vehicles)) {
            $vehicles = $fleetBean->$rel_fleet_vehicles->getBeans();
            
            foreach ($vehicles as $vehicle) {
                $fleetStructure['vehicles'][] = [
                    'make'                   => $vehicle->make,
                    'model'                  => $vehicle->model,
                    'reg'                    => $vehicle->name, // Or registration_number
                    'date_next_mot'          => $vehicle->date_next_mot,
                    'date_last_service'      => $vehicle->date_last_service,
                    'date_next_service'      => $vehicle->date_next_service,
                    'date_last_check'        => $vehicle->date_last_check,
                    'mot_expiry'             => $vehicle->date_mot,
                    'ins_expiry'             => $vehicle->date_registered,
                    'outstanding_issues'     => $this->getVehicleIssues($vehicle)
                ];
            }
        }

        return $fleetStructure;
    }

    /**
     * Finds all fleet administrators and broadcasts the data payload to WordPress
     */
    private function distributeToFleetAdmins($fleetBean, $fleetPayload) {
        // Name of relationship between Fleets and your new Membership module
        $rel_fleet_memberships = 'visp_fleet_visp_fleet_membership';

        if ($fleetBean->load_relationship($rel_fleet_memberships)) {
            $memberships = $fleetBean->$rel_fleet_memberships->getBeans();

            foreach ($memberships as $membership) {
                // Check if the "is_fleetadmin" checkbox is checked (returns 1 or true)
                if (!empty($membership->is_fleetadmin) && $membership->is_fleetadmin == 1) {
                    
                    // Name of relationship between Membership module and Contacts
                    $rel_membership_contact = 'contacts_visp_fleet_membership';
                    
                    if ($membership->load_relationship($rel_membership_contact)) {
                        $contacts = $membership->$rel_membership_contact->getBeans();
                        $contact = reset($contacts); // Get the single contact record linked

                        if ($contact && !empty($contact->email1)) {
                            // Format the wrapper payload exactly how WordPress wants it
                            $wpPayload = [
                                'email'             => $contact->email1,
                                'first_name'        => $contact->first_name,
                                'last_name'         => $contact->last_name,
                                'membership_status' => $contact->membership_status_c ?? 'Active',
                                'fleets'            => [$fleetPayload] // Sent inside an array wrapper
                            ];

                            // Call your existing operational API method
                            $this->callWPAPI($wpPayload);
                        }
                    }
                }
            }
        }
    }

    /**
     * Helper to compile outstanding issues for a single vehicle
     */
    private function getVehicleIssues($vehicleBean) {
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
        }
        return $issuesData;
    }


    private function getVehicleImageUrl($vehicle) {
        // If you are using a Photo field in SuiteCRM, the ID is stored in the field
        // Adjust 'photo_c' to your actual field name
        if (!empty($vehicle->photo_c)) {
            return "https://uatcrm.vinttro.co.uk/upload/" . $vehicle->photo_c;
        }
        return "https://vinttro.co.uk/wp-content/uploads/placeholder-car.png";
    }

    private function callWPAPI($payload) {
        $url = "https://uat.vinttro.co.uk/wp-json/vinttro/v1/create-member/";

        // Use your Application Password credentials
        $username = 'vinttro_admin';
        $app_password = '6YSN vLuS Pa4d kMIk flMY TYoQ';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        // Tell PHP to follow 301/302 redirects automatically
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Since you are on UAT/Self-Signed sometimes:
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$app_password")
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Catch CURL errors (like DNS or connection timeouts)
        if(curl_errno($ch)) {
            $error_msg = curl_error($ch);
            file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - CURL ERROR: $error_msg\n", FILE_APPEND);
        }

        curl_close($ch);

        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - WP Response Code: $http_code | Response: $response\n", FILE_APPEND);
    }
}