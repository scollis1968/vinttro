<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VehicleCheckHook 
{
    public function beforeSaveMethod($bean, $event, $arguments) 
    {
        // 1. Define your relationship link name (Found in Step 1)
        $linkName = 'visp_vehicle_visp_vehicle_check'; 

        // 2. Load the relationship via the join table
        if (!$bean->load_relationship($linkName)) {
            $GLOBALS['log']->fatal("VehicleCheckHook: Failed to load relationship link: $linkName");
            return;
        }

        // Get the ID of the related vehicle from the join table
        $relatedIds = $bean->$linkName->get();
        if (empty($relatedIds) || !is_array($relatedIds)) {
            return; // No vehicle linked to this check yet
        }

        // Fetch the parent vehicle bean using the first ID found
        $vehicleId = reset($relatedIds);
        $vehicle = BeanFactory::getBean('visp_vehicle', $vehicleId);

        if (empty($vehicle) || empty($vehicle->id)) {
            return;
        }

        // 3. Automatically populate the name of the check: [Vehicle Name/Reg] - [yyyy-mm-dd]
        if (!empty($bean->date_of_check)) {
            $checkDate = new DateTime($bean->date_of_check);
            $formattedDate = $checkDate->format('Y-m-d');
            
            $vehicleIdentifier = !empty($vehicle->name) ? $vehicle->name : "Vehicle";
            $bean->name = $vehicleIdentifier . ' - ' . $formattedDate;
        }

        // 4. Update parent vehicle record with latest check data
        $vehicle->date_last_checked = $bean->date_of_check;
        $vehicle->mileage_last_check = $bean->mileage;

        // 5. Calculate and update visp_vehicle.date_next_service
        if (!empty($vehicle->date_last_service)) {
            $dateLastService = new DateTime($vehicle->date_last_service);
            $optionA = null;
            $optionB = null;

            // --- OPTION A: Time-Based Interval ---
            if (!empty($vehicle->service_intervals_months) && (int)$vehicle->service_intervals_months > 0) {
                $optionA = clone $dateLastService;
                $months = (int)$vehicle->service_intervals_months;
                $optionA->modify("+$months months");
            }

            // --- OPTION B: Mileage Run-Rate Projection ---
            if (!empty($bean->date_of_check) && !empty($bean->mileage) && !empty($vehicle->miles_last_service)) {
                $dateOfCheck = new DateTime($bean->date_of_check);
                
                $mileageLastCheck = (float)$bean->mileage;
                $milesLastService = (float)$vehicle->miles_last_service;
                $serviceIntervalMiles = (float)$vehicle->service_interval_miles;

                // Calculate deltas
                $daysElapsed = $dateLastService->diff($dateOfCheck)->days;
                $milesDriven = $mileageLastCheck - $milesLastService;

                if ($dateOfCheck < $dateLastService) {
                    $daysElapsed = -$daysElapsed;
                }

                // Guardrail against division by zero or negative time elapsed
                if ($milesDriven > 0 && $daysElapsed > 0 && $serviceIntervalMiles > 0) {
                    $totalDaysAllowed = $serviceIntervalMiles * ($daysElapsed / $milesDriven);
                    
                    $optionB = clone $dateLastService;
                    $daysToAdd = (int)round($totalDaysAllowed);
                    $optionB->modify("+$daysToAdd days");
                }
            }

            // --- Compare Options & Apply the Lower Date ---
            $finalNextServiceDate = null;

            if ($optionA && $optionB) {
                $finalNextServiceDate = ($optionA < $optionB) ? $optionA : $optionB;
            } elseif ($optionA) {
                $finalNextServiceDate = $optionA;
            } elseif ($optionB) {
                $finalNextServiceDate = $optionB;
            }

            if ($finalNextServiceDate) {
                $vehicle->date_next_service = $finalNextServiceDate->format('Y-m-d');
            }
        }

        // 6. Save the updated parent vehicle bean
        $vehicle->save();
    }
}