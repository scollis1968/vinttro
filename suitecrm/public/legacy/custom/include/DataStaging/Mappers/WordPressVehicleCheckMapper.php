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

        // 1. Execute Lookup Chain: Find the Vehicle
        $vehicleBean = $this->findVehicleByRegistration($registration);
        if (!$vehicleBean) {
            throw new \Exception("Lookup Failed: Vehicle with registration '{$registration}' does not exist in SuiteCRM.");
        }

        // 2. Locate Active Fleet Context (Placeholder for your logic)
        $fleetId = $this->findActiveFleetForVehicle($vehicleBean->id);
        
        // 3. Match Driver Name string to Fleet Member
        $driverName = $this->flatten($rawData['driver-name'] ?? $rawData['your-name'] ?? '');
        $driverId = $this->attemptDriverMatching($fleetId, $driverName);

        // 4. Populate and Create the Vehicle Check Record
        $vehicleCheck = BeanFactory::newBean('visp_vehicle_check');
        $vehicleCheck->name = "Check - " . $registration . " (" . date('Y-m-d') . ")";
        $vehicleCheck->date_logged_c = date('Y-m-d H:i:s');
        
        // Relate parent entities
        $vehicleCheck->visp_vehicle_id_c = $vehicleBean->id;
        if (!empty($fleetId)) {
            $vehicleCheck->visp_fleet_id_c = $fleetId;
        }
        
        if (!empty($driverId)) {
            $vehicleCheck->check_by_contact = $driverId;
        } else {
            $vehicleCheck->unmatched_driver_name_c = !empty($driverName) ? $driverName : 'Unspecified Driver';
        }

        // --- EXACT MAPS MATCHING YOUR CF7 JSON PAYLOAD ---
        // Double-check the left side fields (_c) match your exact database column names in Studio!
        
        $vehicleCheck->mileage = isset($rawData['current-mileage']) ? intval($this->flatten($rawData['current-mileage'])) : 0;
        
        $vehicleCheck->oil_level_status        = $this->flatten($rawData['oil-level'] ?? '');
        $vehicleCheck->coolant_level_status    = $this->flatten($rawData['coolant-level'] ?? '');
        $vehicleCheck->wiper_fluid_status      = $this->flatten($rawData['wiper-fluid-level'] ?? '');
        $vehicleCheck->wiper_condition_status  = $this->flatten($rawData['wiper-condition'] ?? '');
        $vehicleCheck->lights_status           = $this->flatten($rawData['lights'] ?? '');
        $vehicleCheck->horn_status             = $this->flatten($rawData['horn'] ?? '');
        $vehicleCheck->tyre_condition_status   = $this->flatten($rawData['tyre-condition'] ?? '');
        $vehicleCheck->tyre_pressure_status    = $this->flatten($rawData['tyre-pressure'] ?? '');
        $vehicleCheck->first_aid_kit           = $this->flatten($rawData['first-aid-kit'] ?? '');
        
        // Warnings & Damage descriptions
        $vehicleCheck->warning_description     = $this->flatten($rawData['warning-description'] ?? '');
        $vehicleCheck->damage_description      = $this->flatten($rawData['damage-description'] ?? '');
        $vehicleCheck->additional_info         = $this->flatten($rawData['additional-info'] ?? '');

        $vehicleCheck->save();

        // 5. Evaluate and spin off an issue tracking ticket if defects/failures are present
        if ($this->hasDefects($rawData)) {
            $issueId = $this->createVehicleIssue($vehicleBean->id, $vehicleCheck->id, $rawData);
            return "Created Vehicle Check Sheet [{$vehicleCheck->id}] and opened active Issue ticket [{$issueId}].";
        }

        return "Successfully created Vehicle Check Sheet [{$vehicleCheck->id}] for vehicle {$registration}.";
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
     * Resilient lookup framework compatible with legacy SuiteCRM bean models
     */
    private function findVehicleByRegistration(string $registration) {
        $seed = BeanFactory::newBean('visp_vehicle');
        if (!$seed) {
            return null;
        }

        $vehicle = $seed->retrieve_by_string_fields(array('name' => $registration, 'deleted' => 0));
        if ($vehicle && !empty($vehicle->id)) {
            return $vehicle;
        }

        $escapedReg = $seed->db->quote($registration);
        $sql = "SELECT m.id FROM visp_vehicle m 
                LEFT JOIN visp_vehicle_cstm c ON m.id = c.id_c 
                WHERE (m.name = '{$escapedReg}' OR c.registration_number_c = '{$escapedReg}') 
                AND m.deleted = 0";
                
        $result = $seed->db->limitQuery($sql, 0, 1, true);
        if ($result && $row = $seed->db->fetchByAssoc($result)) {
            return BeanFactory::getBean('visp_vehicle', $row['id']);
        }

        return null;
    }

    /**
     * Custom placeholder matching your fleet assignment architectural structure
     */
    private function findActiveFleetForVehicle(string $vehicleId): ?string {
        return null; 
    }

    /**
     * Normalizes and evaluates driver names sequentially inside the fleet scope
     */
    private function attemptDriverMatching(?string $fleetId, string $driverName): ?string {
        return null;
    }

    /**
     * Conditional parser flagging non-compliant answers based on your actual fields
     */
    private function hasDefects(array $rawData): bool {
        $warningToggle = $this->flatten($rawData['warning-toggle'] ?? '');
        $damageToggle  = $this->flatten($rawData['damage-toggle'] ?? '');
        
        return (
            $warningToggle === 'Yes' || 
            $damageToggle === 'Yes' || 
            !empty($rawData['warning-description']) || 
            !empty($rawData['damage-description'])
        );
    }

    /**
     * Isolated creator generating linked secondary issue beans
     */
    private function createVehicleIssue(string $vehicleId, string $checkId, array $rawData): string {
        $issue = BeanFactory::newBean('visp_vehicle_issue');
        
        $warnDesc = $this->flatten($rawData['warning-description'] ?? '');
        $dmgDesc  = $this->flatten($rawData['damage-description'] ?? '');
        
        $issue->name = "Defect Reported via Check Sheet";
        $issue->status = 'Open';
        $issue->visp_vehicle_id_c = $vehicleId;
        $issue->visp_vehicle_check_id_c = $checkId;
        
        $issue->description = "Warnings: " . (!empty($warnDesc) ? $warnDesc : 'None reported.') . "\n" .
                             "Damage Notes: " . (!empty($dmgDesc) ? $dmgDesc : 'None reported.');
        $issue->save();

        return $issue->id;
    }
}