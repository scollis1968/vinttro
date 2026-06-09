<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VehicleCheckHook 
{
    // This flag prevents the hook from running recursively if a child save triggers a parent save
    private static $preventRecursion = false;

    public function beforeSaveMethod($bean, $event, $arguments) 
    {
        // If we are already inside a save cycle initiated by this hook, stop immediately!
        if (self::$preventRecursion) {
            return;
        }

        $linkName = 'visp_vehicle_visp_vehicle_check'; 

        if (!$bean->load_relationship($linkName)) {
            return;
        }

        $relatedIds = $bean->$linkName->get();
        if (empty($relatedIds) || !is_array($relatedIds)) {
            return; 
        }

        $vehicleId = reset($relatedIds);
        $vehicle = BeanFactory::getBean('visp_vehicle', $vehicleId);

        if (empty($vehicle) || empty($vehicle->id)) {
            return;
        }

        // 1. Automatically populate the name of the check
        if (!empty($bean->date_of_check)) {
            $checkDate = new DateTime($bean->date_of_check);
            $bean->name = (!empty($vehicle->name) ? $vehicle->name : "Vehicle") . ' - ' . $checkDate->format('Y-m-d');
        }

        // 2. Set the data on the parent vehicle
        $vehicle->date_last_checked = $bean->date_of_check;
        $vehicle->mileage_last_check = $bean->mileage;

        // 3. Calculate and update service dates
        if (!empty($vehicle->date_last_service)) {
            $dateLastService = new DateTime($vehicle->date_last_service);
            $optionA = null; 
            $optionB = null;

            if (!empty($vehicle->service_intervals_months) && (int)$vehicle->service_intervals_months > 0) {
                $optionA = clone $dateLastService;
                $months = (int)$vehicle->service_intervals_months;
                $optionA->modify("+$months months");
            }

            if (!empty($bean->date_of_check) && !empty($bean->mileage) && !empty($vehicle->miles_last_service)) {
                $dateOfCheck = new DateTime($bean->date_of_check);
                $milesDriven = (float)$bean->mileage - (float)$vehicle->miles_last_service;
                $daysElapsed = $dateLastService->diff($dateOfCheck)->days;
                if ($dateOfCheck < $dateLastService) { $daysElapsed = -$daysElapsed; }

                if ($milesDriven > 0 && $daysElapsed > 0 && (float)$vehicle->service_interval_miles > 0) {
                    $totalDaysAllowed = (float)$vehicle->service_interval_miles * ($daysElapsed / $milesDriven);
                    $optionB = clone $dateLastService;
                    $daysToAdd = (int)round($totalDaysAllowed);
                    $optionB->modify("+$daysToAdd days");
                }
            }

            $finalNextServiceDate = null;
            if ($optionA && $optionB) { $finalNextServiceDate = ($optionA < $optionB) ? $optionA : $optionB; }
            elseif ($optionA) { $finalNextServiceDate = $optionA; }
            elseif ($optionB) { $finalNextServiceDate = $optionB; }

            if ($finalNextServiceDate) {
                $vehicle->date_next_service = $finalNextServiceDate->format('Y-m-d');
            }
        }

        // 4. Safe Save: Turn on the lock, save the parent, then turn off the lock
        self::$preventRecursion = true;
        
        $vehicle->save();
        
        self::$preventRecursion = false;
    }
}