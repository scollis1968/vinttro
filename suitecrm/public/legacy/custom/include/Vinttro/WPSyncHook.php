<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class WPSyncHook {
    
    // Global log file location for total tracking consistency
    private $logFile = '/tmp/vinttro_ladder_debug.log';

    public function syncContactHook($bean, $event, $arguments) {
        $this->executeSyncForContact($bean);
    }

    public function syncVehicleHook($vehicleBean, $event, $arguments) {
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - [syncVehicleHook] triggered for Vehicle ID: " . $vehicleBean->id . "\n", FILE_APPEND);
        
        $rel_name = 'visp_vehicle_contacts'; 

        if ($vehicleBean->load_relationship($rel_name)) {
            $relatedContacts = $vehicleBean->$rel_name->getBeans();
            file_put_contents($this->logFile, "   - [syncVehicleHook] Found " . count($relatedContacts) . " direct contacts linked to vehicle.\n", FILE_APPEND);

            foreach ($relatedContacts as $contact) {
                $this->executeSyncForContact($contact);
            }
        } else {
            file_put_contents($this->logFile, "   - [syncVehicleHook] ❌ Failed loading direct contact relationship '$rel_name'\n", FILE_APPEND);
        }
    }

    /**
     * The single source of truth for gathering data and blasting WP
     */
    public function executeSyncForContact($contactBean) {
        if (empty($contactBean->portal_active_c)) {
            return; 
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
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - syncToWordPress 1 triggered\n", FILE_APPEND);
        
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

        $this->callWPAPI($payload);
    }

    public function syncVehicleFleetUpdate($vehicleBean, $event, $arguments) {
        file_put_contents($this->logFile, "==================================================================\n", FILE_APPEND);
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - [STEP 1] Hook triggered on Vehicle. ID: " . $vehicleBean->id . " | Reg: " . $vehicleBean->name . "\n", FILE_APPEND);

        $rel_vehicle_fleet = 'visp_fleet_visp_vehicle'; 
        file_put_contents($this->logFile, " - Loading Vehicle -> Fleet relationship: '$rel_vehicle_fleet'\n", FILE_APPEND);

        if ($vehicleBean->load_relationship($rel_vehicle_fleet)) {
            $relatedFleets = $vehicleBean->$rel_vehicle_fleet->getBeans();
            file_put_contents($this->logFile, "   🟢 SUCCESS: Found " . count($relatedFleets) . " linked fleet(s).\n", FILE_APPEND);
            
            foreach ($relatedFleets as $fleet) {
                file_put_contents($this->logFile, "   -> Processing Fleet: ID: " . $fleet->id . " | Name: " . $fleet->name . "\n", FILE_APPEND);
                $fleetPayload = $this->compileFleetDataStructure($fleet);
                $this->distributeToFleetAdmins($fleet, $fleetPayload);
            }
        } else {
            file_put_contents($this->logFile, "   ❌ FAILED: Could not load link '$rel_vehicle_fleet' on Vehicle bean.\n", FILE_APPEND);
        }
    }

    private function compileFleetDataStructure($fleetBean) {
        file_put_contents($this->logFile, " - [STEP 2] Compiling data structure for Fleet: " . $fleetBean->name . "\n", FILE_APPEND);

        $fleetStructure = [
            'name'     => $fleetBean->name,
            'vehicles' => []
        ];

        $rel_fleet_vehicles = 'visp_fleet_visp_vehicle';
        
        if ($fleetBean->load_relationship($rel_fleet_vehicles)) {
            $vehicles = $fleetBean->$rel_fleet_vehicles->getBeans();
            file_put_contents($this->logFile, "     🟢 SUCCESS: Found " . count($vehicles) . " vehicle(s) inside this fleet.\n", FILE_APPEND);
            
            foreach ($vehicles as $vehicle) {
                $fleetStructure['vehicles'][] = [
                    'id'                     => $vehicle->id,   
                    'make'                   => $vehicle->make,
                    'model'                  => $vehicle->model,
                    'reg'                    => $vehicle->name,
                    'date_last_check'        => $vehicle->date_last_check,
                    'date_last_mot'          => $vehicle->date_last_mot,
                    'date_last_service'      => $vehicle->date_last_service,
                    'date_last_tax'          => $vehicle->date_last_tax,
                    'date_next_mot'          => $vehicle->date_next_mot,
                    'date_next_service'      => $vehicle->date_next_service,
                    'date_next_tax'          => $vehicle->date_next_tax,
                    'date_ins_renewal'       => $vehicle->date_ins_renewal,
                    'outstanding_issues'     => $this->getVehicleIssues($vehicle)
                ];
            }
        } else {
            file_put_contents($this->logFile, "     ❌ FAILED: Could not load link '$rel_fleet_vehicles' on Fleet bean.\n", FILE_APPEND);
        }

        return $fleetStructure;
    }

    private function distributeToFleetAdmins($fleetBean, $fleetPayload) {
        file_put_contents($this->logFile, " - [STEP 3] Distributing updates for Fleet: " . $fleetBean->name . "\n", FILE_APPEND);

        $rel_fleet_memberships = 'visp_fleet_visp_fleet_memberships';

        if ($fleetBean->load_relationship($rel_fleet_memberships)) {
            $memberships = $fleetBean->$rel_fleet_memberships->getBeans();
            file_put_contents($this->logFile, "     🟢 SUCCESS: Found " . count($memberships) . " total personnel records.\n", FILE_APPEND);

            foreach ($memberships as $membership) {
                if (!empty($membership->is_fleetadmin) && ($membership->is_fleetadmin == '1' || $membership->is_fleetadmin == 1 || $membership->is_fleetadmin === true)) {
                    
                    $target_relationship = 'contacts_visp_fleet_memberships_1';
                    $rel_membership_contact = '';
                    
                    foreach ($membership->field_defs as $fieldName => $def) {
                        if (isset($def['type']) && $def['type'] === 'link' && isset($def['relationship']) && $def['relationship'] === $target_relationship) {
                            $rel_membership_contact = $fieldName;
                            break;
                        }
                    }

                    if (empty($rel_membership_contact)) {
                        foreach ($membership->field_defs as $fieldName => $def) {
                            if (isset($def['type']) && $def['type'] === 'link' && strpos(strtolower($fieldName), 'contact') !== false) {
                                $rel_membership_contact = $fieldName;
                                break;
                            }
                        }
                    }
                    
                    if (!empty($rel_membership_contact) && $membership->load_relationship($rel_membership_contact)) {
                        $contacts = $membership->$rel_membership_contact->getBeans();
                        $contact = reset($contacts); 

                        if ($contact && !empty($contact->email1)) {
                            file_put_contents($this->logFile, "          🚀 DISPATCHING API: Targeting Admin Email: " . $contact->email1 . "\n", FILE_APPEND);
                            
                            $wpPayload = [
                                'email'             => $contact->email1,
                                'first_name'        => $contact->first_name,
                                'last_name'         => $contact->last_name,
                                'membership_status' => $contact->membership_status_c ?? 'Active',
                                'fleets'            => [$fleetPayload]
                            ];

                            // 🛠️ TRAP RESPONSE: Execute API call and capture metrics inside the loop context
                            $result = $this->callWPAPI($wpPayload);

                            if ($result['success']) {
                                file_put_contents($this->logFile, "          ✅ DISPATCH SUCCESS for: " . $contact->email1 . " | Response Code: " . $result['http_code'] . "\n", FILE_APPEND);
                            } else {
                                file_put_contents($this->logFile, "          ❌ DISPATCH CRITICAL FAILURE for: " . $contact->email1 . " | HTTP Code: " . $result['http_code'] . " | Response Msg: " . $result['response'] . " | Error: " . $result['error'] . "\n", FILE_APPEND);
                            }
                        } else {
                            file_put_contents($this->logFile, "          ⚠️ WARNING: Contact found but Email field (email1) is blank.\n", FILE_APPEND);
                        }
                    } else {
                        file_put_contents($this->logFile, "          ❌ FAILED: Could not load link field '$rel_membership_contact' on Membership bean.\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents($this->logFile, "        skipping: User [" . $membership->name . "] is not marked as a Fleet Admin.\n", FILE_APPEND);
                }
            }
        } else {
            file_put_contents($this->logFile, "     ❌ FAILED: Could not load link '$rel_fleet_memberships' on Fleet bean.\n", FILE_APPEND);
        }
        file_put_contents($this->logFile, "          ✅  ALL members processed.\n", FILE_APPEND);
    }

    private function getVehicleIssues($vehicleBean) {
        $issuesData = [];
        $rel_vehicle_issues = 'visp_vehicle_visp_vehicle_issue'; 
        
        if ($vehicleBean->load_relationship($rel_vehicle_issues)) {
            $issues = $vehicleBean->$rel_vehicle_issues->getBeans();
            foreach ($issues as $issue) {
                $issuesData[] = [
                    'name'                => $issue->name,
                    'description'         => $issue->description,
                    'severity'            => $issue->severity, 
                    'date_issue_reported' => $issue->date_entered
                ];
            }
        }
        return $issuesData;
    }

    private function getVehicleImageUrl($vehicle) {
        if (!empty($vehicle->photo_c)) {
            return "https://uatcrm.vinttro.co.uk/upload/" . $vehicle->photo_c;
        }
        return "https://vinttro.co.uk/wp-content/uploads/placeholder-car.png";
    }

    // Placeholders to retain structural integrity of your sync mapping execution
    private function getIndividualGarage($contactBean) { return []; }
    private function getRelatedFleets($contactBean) { return []; }

    /**
     * Diagnostic API Processor
     * Returns execution stats array instead of returning void
     */
    private function callWPAPI($payload) {
        // 1. Parse Env Variables
        $url = $_ENV['WP_API_URL'] ?? getenv('WP_API_URL') ?? null;
        $username = $_ENV['WP_API_USERNAME'] ?? getenv('WP_API_USERNAME') ?? null;
        $app_password = $_ENV['WP_API_APP_PASSWORD'] ?? getenv('WP_API_APP_PASSWORD') ?? null;

        // 2. Fallback to Legacy configuration if Env variables are dropped by framework bridge
        global $sugar_config;
        if (!$username || !$app_password) {
            $username = $sugar_config['wp_api']['username'] ?? '';
            $app_password = $sugar_config['wp_api']['app_password'] ?? '';
        }
        if (!$url) {
            $url = $sugar_config['wp_api']['url'] ?? '';
        }

        // 🛡️ CRITICAL ERROR TRAPPING: Halt safely if variables are blank
        if (empty($url) || empty($username) || empty($app_password)) {
            $error_details = "Missing components -> URL: " . ($url ? 'OK' : 'EMPTY') . " | User: " . ($username ? 'OK' : 'EMPTY') . " | Pass: " . ($app_password ? 'OK' : 'EMPTY');
            return [
                'success'   => false,
                'http_code' => 0,
                'response'  => 'Local Execution Halt',
                'error'     => $error_details
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);        

        $ch_headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$app_password")
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $ch_headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = null;

        if (curl_errno($ch)) {
            $curl_error = curl_error($ch);
        }

        curl_close($ch);

        return [
            'success'   => ($http_code >= 200 && $http_code < 300),
            'http_code' => $http_code,
            'response'  => $response,
            'error'     => $curl_error ?? 'None'
        ];
    }
}