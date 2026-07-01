<?php
namespace Custom\DataStaging\Mappers;

use Custom\DataStaging\AbstractStagingMapper;
use BeanFactory;

class WordPressVehicleCheckMapper extends AbstractStagingMapper {

    /**
     * Aligned process method matching your scheduler's exact footprint
     */
    public function process(array $rawData, \SugarBean $stagingRecord): string {
        
        if (empty($rawData)) {
            throw new \Exception("Decoded raw payload data is empty.");
        }

        // Extract and sanitize registration number
        $registration = strtoupper(trim($this->flatten($rawData['registration'] ?? '')));
        if (empty($registration)) {
            throw new \Exception("Missing core vehicle registration field in form data submission.");
        }

        // 1. Execute Lookup Chain: Find the Vehicle ID string directly
        $vehicleId = $this->findVehicleByRegistration($registration);
        if (!$vehicleId) {
            throw new \Exception("Lookup Failed: Vehicle with registration '{$registration}' does not exist in SuiteCRM.");
        }

        // 2. Locate Active Fleet Context (Passes the flat string ID)
        $fleetId = $this->findActiveFleetForVehicle($vehicleId);
        
        // 3. Match Driver Name string to Fleet Member
        $driverName = $this->flatten($rawData['driver-name'] ?? $rawData['your-name'] ?? '');
        $driverId = $this->attemptDriverMatching($fleetId, $driverName);

        // 4. Populate and Create the Vehicle Check Record
        $vehicleCheck = BeanFactory::newBean('visp_vehicle_check');
        $vehicleCheck->name = "Check - " . $registration . " (" . date('Y-m-d') . ")";
        $vehicleCheck->date_logged_c = date('Y-m-d H:i:s');
        
        // --- DEFENSIVE DATE OF CHECK POPULATION ---
        $formDate = $this->flatten($rawData['date-of-check'] ?? $rawData['check-date'] ?? '');
        $finalCheckDate = !empty($formDate) ? $formDate : date('Y-m-d');
        
        $vehicleCheck->date_of_check = $finalCheckDate;
        $vehicleCheck->date_of_check_c = $finalCheckDate;

        // Relate parent entities
        if (!empty($fleetId)) {
            $vehicleCheck->visp_fleet_id = $fleetId;
        }
        
        if (!empty($driverId)) {
            $vehicleCheck->check_by_contact = $driverId;
        } else {
            $vehicleCheck->check_by = !empty($driverName) ? $driverName : 'Unspecified Driver';
        }

        // --- MAPS MATCHING YOUR CF7 JSON PAYLOAD ---
        $vehicleCheck->mileage = isset($rawData['current-mileage']) ? intval($this->flatten($rawData['current-mileage'])) : 11;
        
        $vehicleCheck->oil_level_ok            = $this->flatten($rawData['oil-level'] ?? '');
        $vehicleCheck->coolant_level_ok        = $this->flatten($rawData['coolant-level'] ?? '');
        $vehicleCheck->wiper_fluid_ok          = $this->flatten($rawData['wiper-fluid-level'] ?? '');
        $vehicleCheck->wiper_condition_good    = $this->flatten($rawData['wiper-condition'] ?? '');
        $vehicleCheck->indicators_ok           = $this->flatten($rawData['indicators'] ?? '');
        $vehicleCheck->lights_ok               = $this->flatten($rawData['lights'] ?? '');
        $vehicleCheck->horn_ok                 = $this->flatten($rawData['horn'] ?? '');
        $vehicleCheck->tyre_condition          = $this->flatten($rawData['tyre-condition'] ?? '');
        $vehicleCheck->tyre_pressure_ok        = $this->flatten($rawData['tyre-pressure'] ?? '');
        $vehicleCheck->first_aid_kit_ok        = $this->flatten($rawData['first-aid-kit'] ?? '');
        $vehicleCheck->any_warning_lights      = $this->flatten($rawData['warning-lights'] ?? '');
        
        $vehicleCheck->issue_description       = $this->flatten($rawData['issue-description'] ?? '');
        $vehicleCheck->damage_description      = $this->flatten($rawData['damage-description'] ?? '');
        $vehicleCheck->additional_info         = $this->flatten($rawData['additional-info'] ?? '');


        // --- DYNAMICALLY DERIVE "ANY_ISSUES" FROM ALL CHECK DROPDOWNS ---
        $checkFields = [
            'oil-level', 'coolant-level', 'wiper-fluid-level', 'wiper-condition',
            'indicators', 'lights', 'horn', 'tyre-condition', 'tyre-pressure',
            'first-aid-kit', 'warning-lights'
        ];

        $hasIssue = false;
        $hasOk = false;

        foreach ($checkFields as $field) {
            $val = strtolower($this->flatten($rawData[$field] ?? ''));
            if ($val === 'issue') {
                $hasIssue = true;
            } elseif ($val === 'ok') {
                $hasOk = true;
            }
        }

        if ($hasIssue) {
            $vehicleCheck->any_issues = 'yes';
        } elseif ($hasOk) {
            $vehicleCheck->any_issues = 'no';
        } else {
            $vehicleCheck->any_issues = ''; // Keeps it Null/Unanswered if nothing was affirmatively filled out
        }

        // --- NORMALIZE "ANY_DAMAGE" FROM CF7 RADIO TO SUITECRM DROPDOWN KEY ---
        $damageToggle = strtolower($this->flatten($rawData['damage-toggle'] ?? ''));
        if ($damageToggle === 'yes') {
            $vehicleCheck->any_damage = 'yes';
        } elseif ($damageToggle === 'no') {
            $vehicleCheck->any_damage = 'no';
        } else {
            $vehicleCheck->any_damage = ''; // Keeps it Null/Unanswered
        }

        // CRITICAL STEP 1: Save the record first to generate its record ID
        $vehicleCheck->save();

        // CRITICAL STEP 2: Handle the Join-Table Relationship after saving
        $linkName = 'visp_vehicle_visp_vehicle_check'; 

        if ($vehicleCheck->load_relationship($linkName)) {
            $vehicleCheck->$linkName->add($vehicleId);
        } else {
            $fallbackLinkName = 'visp_vehicle_visp_vehicle_check_1';
            if ($vehicleCheck->load_relationship($fallbackLinkName)) {
                $vehicleCheck->$fallbackLinkName->add($vehicleId);
            } else {
                $linkedFields = $vehicleCheck->get_linked_fields();
                $availableLinks = array_keys($linkedFields);
                $linksListString = implode(', ', $availableLinks);

                throw new \Exception("Relationship Link Name could not be loaded. Tested names '{$linkName}' and '{$fallbackLinkName}' failed. Available link names on this module are: [{$linksListString}]");
            }
        }

        // 5. Evaluate and spin off an issue tracking ticket if defects/failures are present
        if ($this->hasDefects($vehicleCheck->any_issues, $vehicleCheck->any_damage, $rawData)) {
            $issueId = $this->createVehicleIssue($vehicleId, $vehicleCheck->id, $rawData);
            return "Created Vehicle Check Sheet [{$vehicleCheck->id}], linked to Vehicle, and opened active Issue ticket [{$issueId}].";
        }

        return "Successfully created Vehicle Check Sheet [{$vehicleCheck->id}] linked to vehicle {$registration}.";
    }

    /**
     * Safely flattens typical Contact Form 7 array strings down to flat text values
     */
    private function flatten($value): string {
        if (is_array($value)) {
            return !empty($value) ? trim((string) $value[0]) : '';
        }
        return trim((string) $value);
    }

    /**
     * Resilient lookup framework returning ONLY the record ID string
     */
    private function findVehicleByRegistration(string $registration): ?string {
        $seed = BeanFactory::newBean('visp_vehicle');
        if (!$seed) {
            return null;
        }

        $vehicle = $seed->retrieve_by_string_fields(array('name' => $registration, 'deleted' => 0));
        if ($vehicle && !empty($vehicle->id)) {
            return $vehicle->id; 
        }

        $escapedReg = $seed->db->quote($registration);
        $sql = "SELECT m.id FROM visp_vehicle m 
                LEFT JOIN visp_vehicle_cstm c ON m.id = c.id_c 
                WHERE (m.name = '{$escapedReg}' OR c.registration_number_c = '{$escapedReg}') 
                AND m.deleted = 0";
                
        $result = $seed->db->limitQuery($sql, 0, 1, true);
        if ($result && $row = $seed->db->fetchByAssoc($result)) {
            return $row['id']; 
        }

        return null;
    }

    private function findActiveFleetForVehicle(string $vehicleId): ?string {
        return null; 
    }

    /**
     * Matches a driver name string to a Contact ID within the scope of a specific Fleet.
     * Includes a global fallback to the Contacts module if no fleet context is found.
     */
    private function attemptDriverMatching(?string $fleetId, string $driverName): ?string {
        if (empty($driverName)) {
            return null;
        }

        $driverNameClean = strtolower(trim($driverName));

        // STRATEGY 1: Contextual Matching within the Fleet
        if (!empty($fleetId)) {
            $fleet = BeanFactory::getBean('visp_fleet', $fleetId);
            
            // Load the 1-to-Many relationship to fleet memberships
            if ($fleet && $fleet->load_relationship('visp_fleet_visp_fleet_membership')) {
                $memberships = $fleet->visp_fleet_visp_fleet_membership->getBeans();
                
                foreach ($memberships as $membership) {
                    // Load the Many-to-1 relationship from membership to contact
                    if ($membership->load_relationship('visp_fleet_memberships_contacts')) {
                        $contacts = $membership->visp_fleet_memberships_contacts->getBeans();
                        
                        foreach ($contacts as $contact) {
                            $fullName = strtolower(trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')));
                            
                            if ($fullName === $driverNameClean) {
                                return $contact->id;
                            }
                        }
                    }
                }
            }
        }

        // STRATEGY 2: Global Fallback
        // If the fleet check fails or $fleetId wasn't found, check the core contacts table directly
        $contactSeed = BeanFactory::newBean('Contacts');
        if ($contactSeed) {
            $escapedName = $contactSeed->db->quote($driverNameClean);
            $sql = "SELECT id FROM contacts 
                    WHERE deleted = 0 
                    AND LOWER(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) = '{$escapedName}'";
                    
            $result = $contactSeed->db->limitQuery($sql, 0, 1, true);
            if ($result && $row = $contactSeed->db->fetchByAssoc($result)) {
                return $row['id'];
            }
        }

        return null;
    }

    /**
     * Conditional parser flagging non-compliant answers based on your calculated derived fields
     */
    private function hasDefects(string $anyIssues, string $anyDamage, array $rawData): bool {
        return (
            $anyIssues === 'yes' || 
            $anyDamage === 'yes' || 
            !empty($this->flatten($rawData['issue-description'] ?? '')) || 
            !empty($this->flatten($rawData['damage-description'] ?? ''))
        );
    }

    /**
     * Isolated creator generating linked secondary issue beans
     */
    private function createVehicleIssue(string $vehicleId, string $checkId, array $rawData): string {
        $issue = BeanFactory::newBean('visp_vehicle_issue');
        
        $issueDesc = $this->flatten($rawData['issue-description'] ?? '');
        $dmgDesc   = $this->flatten($rawData['damage-description'] ?? '');
        
        $issue->name = "Defect Reported via Check Sheet";
        $issue->status = 'Open';
        $issue->visp_vehicle_id_c = $vehicleId;
        $issue->visp_vehicle_check_id_c = $checkId;
        
        $issue->description = "Issues: " . (!empty($issueDesc) ? $issueDesc : 'None reported.') . "\n" .
                             "Damage Notes: " . (!empty($dmgDesc) ? $dmgDesc : 'None reported.');
        $issue->save();

        return $issue->id;
    }
}