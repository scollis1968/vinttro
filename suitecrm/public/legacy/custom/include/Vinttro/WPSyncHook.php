<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
// Require the logger utility
require_once 'custom/include/Vinttro/VinttroLogger.php';

class WPSyncHook {
    
    public function syncContactHook($bean, $event, $arguments) {
        $this->executeSyncForContact($bean);
    }

    public function syncVehicleHook($vehicleBean, $event, $arguments) {
        // FIX: Fixed undefined variable reference
        VinttroLogger::fatal("[syncVehicleHook] triggered for Vehicle ID:: " . $vehicleBean->id);
        $rel_name = 'visp_vehicle_contacts'; 

        if ($vehicleBean->load_relationship($rel_name)) {
            $relatedContacts = $vehicleBean->$rel_name->getBeans();
            VinttroLogger::fatal("[syncVehicleHook] Found " . count($relatedContacts) . " direct contacts linked to vehicle.");

            foreach ($relatedContacts as $contact) {
                $this->executeSyncForContact($contact);
            }
        } else {
            VinttroLogger::fatal("[syncVehicleHook] ❌ Failed loading direct contact relationship '$rel_name'");
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
        // FIX: Fixed undefined variable reference
        VinttroLogger::fatal("[syncToWordpress] triggered for Contact ID:: " . $bean->id);

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
        static $hasRun = false;

        VinttroLogger::fatal("[syncVehicleFleetUpdate] [START] ID: " . $vehicleBean->id);

        if ($hasRun) {
            VinttroLogger::fatal("[syncVehicleFleetUpdate] [EXITING] Already processed in this request.");
            return;
        }

        $hasRun = true;
        VinttroLogger::fatal("[syncVehicleFleetUpdate] [SETTING FLAG] Running primary logic...");
        VinttroLogger::fatal("[syncVehicleFleetUpdate] [STEP 1] Loading fleet relationship...");
        
        $rel_vehicle_fleet = 'visp_fleet_visp_vehicle';

        if ($vehicleBean->load_relationship($rel_vehicle_fleet)) {
            $relatedFleets = $vehicleBean->$rel_vehicle_fleet->getBeans();
            VinttroLogger::fatal("[syncVehicleFleetUpdate] Found " . count($relatedFleets) . " linked fleet(s).");
            
            foreach ($relatedFleets as $fleet) {
                VinttroLogger::fatal("[syncVehicleFleetUpdate] Processing Fleet: ID: " . $fleet->id . " | Name: " . $fleet->name);
                $fleetPayload = $this->compileFleetDataStructure($fleet);
                $this->distributeToFleetAdmins($fleet, $fleetPayload);
            }
        } else {
            VinttroLogger::fatal("[syncVehicleFleetUpdate] ❌ FAILED: Could not load link '$rel_vehicle_fleet' on Vehicle bean.");
        }
    }

    private function compileFleetDataStructure($fleetBean) {
        VinttroLogger::fatal("[compileFleetDataStructure] Compiling data structure for Fleet: " . $fleetBean->name);

        $fleetStructure = [
            'name'     => $fleetBean->name,
            'vehicles' => []
        ];

        $rel_fleet_vehicles = 'visp_fleet_visp_vehicle';
        
        if ($fleetBean->load_relationship($rel_fleet_vehicles)) {
            $vehicles = $fleetBean->$rel_fleet_vehicles->getBeans();
            VinttroLogger::fatal("[compileFleetDataStructure] 🟢 SUCCESS: Found " . count($vehicles) . " vehicle(s) inside this fleet.");

            foreach ($vehicles as $vehicle) {
                $data = [
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
                
                $data['main_driver'] = 'Unassigned';
                $data['main_driver_phone'] = '';
                $data['main_driver_email'] = '';
                
                $contact_id = $vehicle->contact_id_c ?? null; 

                if (!empty($contact_id)) {
                    $contact = BeanFactory::getBean('Contacts', $contact_id);
                    if ($contact && !empty($contact->id)) {
                        $data['main_driver'] = trim($contact->first_name . ' ' . $contact->last_name);
                        $data['main_driver_phone'] = $contact->phone_mobile ?: ($contact->phone_work ?: '');
                        $data['main_driver_email'] = $contact->email1 ?? '';
                    }
                }
                $fleetStructure['vehicles'][] = $data;  
            }
        } else {
            VinttroLogger::fatal("[compileFleetDataStructure] ❌ FAILED: Could not load link '$rel_fleet_vehicles' on Fleet bean.");
        }

        return $fleetStructure;
    }

    private function distributeToFleetAdmins($fleetBean, $fleetPayload) {
        VinttroLogger::fatal("[distributeToFleetAdmins] Distributing updates for Fleet: " . $fleetBean->name);

        $rel_fleet_memberships = 'visp_fleet_visp_fleet_memberships';

        if ($fleetBean->load_relationship($rel_fleet_memberships)) {
            $memberships = $fleetBean->$rel_fleet_memberships->getBeans();
            VinttroLogger::fatal("[distributeToFleetAdmins] 🟢 SUCCESS: Found " . count($memberships) . " total personnel records.");

            // OPTIMIZATION: Keep track of processed emails to prevent multi-admin duplicated API bursts
            $processedEmails = [];

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
                            $email = trim($contact->email1);

                            // OPTIMIZATION: If we already messaged this email in this execution slice, skip it!
                            if (in_array($email, $processedEmails)) {
                                VinttroLogger::fatal("[distributeToFleetAdmins] Skipping duplicate admin communications path for: " . $email);
                                continue;
                            }
                            $processedEmails[] = $email;

                            VinttroLogger::fatal("[distributeToFleetAdmins] 🚀 DISPATCHING API: Targeting Admin Email: " . $email . "\n");

                            $wpPayload = [
                                'email'             => $email,
                                'first_name'        => $contact->first_name,
                                'last_name'         => $contact->last_name,
                                'membership_status' => $contact->membership_status_c ?? 'Active',
                                'fleets'            => [$fleetPayload]
                            ];

                            $result = $this->callWPAPI($wpPayload);

                            if ($result['success']) {
                                VinttroLogger::fatal("[distributeToFleetAdmins] ✅ DISPATCH SUCCESS for: " . $email . " | Response Code: " . $result['http_code'] . "\n");
                            } else {
                                VinttroLogger::fatal("[distributeToFleetAdmins] ❌ DISPATCH CRITICAL FAILURE for: " . $email . " | HTTP Code: " . $result['http_code'] . " | Response Msg: " . $result['response'] . " | Error: " . $result['error'] . "\n");
                            }
                        } else {
                            VinttroLogger::fatal("[distributeToFleetAdmins] ⚠️ WARNING: Contact found but Email field (email1) is blank.\n");
                        }
                    } else {
                        VinttroLogger::fatal("[distributeToFleetAdmins] ❌ FAILED: Could not load link field '$rel_membership_contact' on Membership bean.\n");
                    }
                } else {
                    VinttroLogger::fatal("[distributeToFleetAdmins]         skipping: User [" . $membership->name . "] is not marked as a Fleet Admin.\n");
                }
            }
        } else {
            VinttroLogger::fatal("[distributeToFleetAdmins]     ❌ FAILED: Could not load link '$rel_fleet_memberships' on Fleet bean.\n");
        }
        VinttroLogger::fatal("[distributeToFleetAdmins]          ✅  ALL members processed.\n");
    }

    private function getVehicleIssues($vehicleBean) {
        $issuesData = [];
        $rel_vehicle_issues = 'visp_vehicle_visp_vehicle_issue'; 
        
        if ($vehicleBean->load_relationship($rel_vehicle_issues)) {
            $issues = $vehicleBean->$rel_vehicle_issues->getBeans();
            foreach ($issues as $issue) {
                if (empty($issue->date_resolved)) {
                    $issuesData[] = [
                        'id'                  => $issue->id,
                        'name'                => $issue->name,
                        'description'         => $issue->description,
                        'severity'            => $issue->severity,  
                        'date_issue_reported' => $issue->date_entered
                    ];
                }
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

    private function getIndividualGarage($contactBean) { return []; }
    private function getRelatedFleets($contactBean) { return []; }

    /**
     * Diagnostic API Processor with Optimized Real-time HTTP Timeouts
     */
    private function callWPAPI($payload) {
        $url = $_ENV['WP_API_URL'] ?? getenv('WP_API_URL') ?? null;
        $username = $_ENV['WP_API_USERNAME'] ?? getenv('WP_API_USERNAME') ?? null;
        $app_password = $_ENV['WP_API_APP_PASSWORD'] ?? getenv('WP_API_APP_PASSWORD') ?? null;

        global $sugar_config;
        if (!$username || !$app_password) {
            $username = $sugar_config['wp_api']['username'] ?? '';
            $app_password = $sugar_config['wp_api']['app_password'] ?? '';
        }
        if (!$url) {
            $url = $sugar_config['wp_api']['url'] ?? '';
        }

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
        
        // OPTIMIZATION: Drastically cut down timeouts so slow network routes can't freeze the CRM front-end
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);        

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