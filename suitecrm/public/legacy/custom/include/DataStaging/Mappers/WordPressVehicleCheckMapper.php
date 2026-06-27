<?php
namespace Custom\DataStaging\Mappers;

use Custom\DataStaging\AbstractStagingMapper;
use BeanFactory;

class WordPressVehicleCheckMapper extends AbstractStagingMapper {

    /**
     * Aligned process method matching your scheduler's exact footprint
     *
     * @param array $rawData The pre-decoded array from your scheduler
     * @param \SugarBean $stagingRecord The parent staging record bean
     * @return string Success message to be written to the tracking field
     */
    public function process(array $rawData, \SugarBean $stagingRecord): string {
        
        if (empty($rawData)) {
            throw new \Exception("Decoded raw payload data is empty.");
        }

        // Extract and sanitize registration number
        $registration = strtoupper(trim($rawData['vehicle-reg__1'] ?? $rawData['vehicle_reg'] ?? $rawData['registration'] ?? ''));
        if (empty($registration)) {
            throw new \Exception("Missing core vehicle registration field in form data submission.");
        }

        // 1. Execute Lookup Chain: Find the Vehicle
        $vehicleBean = $this->findVehicleByRegistration($registration);
        if (!$vehicleBean) {
            throw new \Exception("Lookup Failed: Vehicle with registration '{$registration}' does not exist in SuiteCRM.");
        }

        // 2. Locate Active Fleet Context
        $fleetId = $this->findActiveFleetForVehicle($vehicleBean->id);
        
        // 3. Match Driver Name string to Fleet Member
        $driverName = $rawData['driver-name'] ?? $rawData['your-name'] ?? '';
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
            $vehicleCheck->driver_id_c = $driverId;
        } else {
            // Fallback field so accountability isn't lost if lookup drops out
            $vehicleCheck->unmatched_driver_name_c = !empty($driverName) ? $driverName : 'Unspecified Driver';
        }

        // Map basic metrics from your CF7 payload
        $vehicleCheck->mileage_c = isset($rawData['mileage']) ? intval($rawData['mileage']) : 0;
        $vehicleCheck->oil_level_status_c = $rawData['oil-status'] ?? '';
        $vehicleCheck->tyre_condition_status_c = $rawData['tyre-status'] ?? '';
        // Add additional check sheet field mappings here...

        $vehicleCheck->save();

        // 5. Evaluate and spin off an issue tracking ticket if defaults/failures are present
        if ($this->hasDefects($rawData)) {
            $issueId = $this->createVehicleIssue($vehicleBean->id, $vehicleCheck->id, $rawData);
            return "Created Vehicle Check Sheet [{$vehicleCheck->id}] and opened active Issue ticket [{$issueId}].";
        }

        return "Successfully created Vehicle Check Sheet [{$vehicleCheck->id}] for vehicle {$registration}.";
    }

    /**
     * Query utility targeting registration text elements
     */
    private function findVehicleByRegistration(string $registration) {
        // Explicitly pull in the core query builder class definition file
        require_once 'include/SugarQuery/SugarQuery.php';
        
        $seed = BeanFactory::newBean('visp_vehicle');
        
        // Use global namespace escape backslash \
        $query = new \SugarQuery();
        $query->from($seed);
        $query->select(['id']);
        $query->where()->equals('registration_number_c', $registration);
        $query->limit(1);

        $results = $query->execute();
        if (!empty($results)) {
            return BeanFactory::getBean('visp_vehicle', $results[0]['id']);
        }
        return null;
    }

    /**
     * Custom placeholder matching your fleet assignment architectural structure
     */
    private function findActiveFleetForVehicle(string $vehicleId): ?string {
        // Implement the relationship mapping query for your environment
        return null; 
    }

    /**
     * Normalizes and evaluates driver names sequentially inside the fleet scope
     */
    private function attemptDriverMatching(?string $fleetId, string $driverName): ?string {
        if (empty($fleetId) || empty($driverName)) {
            return null;
        }
        // Run lookups inside visp_fleet_members matching name strings
        return null;
    }

    /**
     * Conditional parser flagging non-compliant answers
     */
    private function hasDefects(array $rawData): bool {
        // Adapt logic rules to flag failures or text entries in your form
        return (
            ($rawData['bodywork-condition'] ?? '') === 'Fail' || 
            !empty($rawData['defect-notes'])
        );
    }

    /**
     * Isolated creator generating linked secondary issue beans
     */
    private function createVehicleIssue(string $vehicleId, string $checkId, array $rawData): string {
        $issue = BeanFactory::newBean('visp_vehicle_issue');
        $issue->name = "Defect Reported: " . ($rawData['defect-summary'] ?? 'Check Sheet Alert');
        $issue->status = 'Open';
        $issue->visp_vehicle_id_c = $vehicleId;
        $issue->visp_vehicle_check_id_c = $checkId;
        $issue->description = $rawData['defect-notes'] ?? 'A defect was noted during a digital vehicle check submission.';
        $issue->save();

        return $issue->id;
    }
}