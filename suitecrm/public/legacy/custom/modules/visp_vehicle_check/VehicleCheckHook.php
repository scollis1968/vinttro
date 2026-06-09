<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VehicleCheckHook 
{
    public function beforeSaveMethod($bean, $event, $arguments) 
    {
        // 1. Fetch the parent vehicle record
        // NOTE: Adjust 'visp_vehicle_id_c' if your relationship field uses a different database name
        if (empty($bean->visp_vehicle_id_c)) {
            return;
        }

        $vehicle = BeanFactory::getBean('visp_vehicle', $bean->visp_vehicle_id_c);
        if (empty($vehicle->id)) {
            return;
        }

        // 2. Automatically populate the name of the check: [Vehicle Name/Reg] - [yyyy-mm-dd]
        if (!empty($bean->date_of_check)) {
            $checkDate = new DateTime($bean->date_of_check);
            $formattedDate = $checkDate->format('Y-m-d');
            
            // Fallback to a generic title if the vehicle name isn't set yet
            $vehicleIdentifier = !empty($vehicle->name) ? $vehicle->name : "Vehicle";
            $bean->name = $vehicleIdentifier . ' - ' . $formattedDate;
        }

        // 3. Update parent vehicle record with latest check data
        $vehicle->date_last_checked = $bean->date_of_check;
        $vehicle->mileage_last_check = $bean->mileage;

        // 4. Calculate and update visp_vehicle.date_next_service
        if (!empty($vehicle->date_last_service)) {
            $dateLastService = new DateTime($vehicle->date_last_service);
            $optionA = null;
            $optionB = null;

            // --- OPTION A: Time-Based Interval (Last Service + Service Interval Months) ---
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

                // Check if the check date is after the service date
                if ($dateOfCheck < $dateLastService) {
                    $daysElapsed = -$daysElapsed;
                }

                // Guardrails: Only calculate run-rate if vehicle has actually been driven 
                // and time has passed since last service to avoid division by zero.
                if ($milesDriven > 0 && $daysElapsed > 0 && $serviceIntervalMiles > 0) {
                    // Total days allowed for this interval based on run-rate
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

        // 5. Save the updated parent vehicle bean
        $vehicle->save();
    }
}