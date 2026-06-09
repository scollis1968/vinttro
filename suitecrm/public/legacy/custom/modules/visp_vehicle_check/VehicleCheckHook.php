<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VehicleCheckHook 
{
    public function beforeSaveMethod($bean, $event, $arguments) 
    {
        $linkName = 'visp_vehicle_visp_vehicle_check'; 

        if (!$bean->load_relationship($linkName)) {
            $GLOBALS['log']->fatal("VCHook DBG: Failed to load relationship link: $linkName");
            return;
        }

        $relatedIds = $bean->$linkName->get();
        if (empty($relatedIds) || !is_array($relatedIds)) {
            $GLOBALS['log']->fatal("VCHook DBG: No related vehicle IDs found in join table.");
            return; 
        }

        $vehicleId = reset($relatedIds);
        $vehicle = BeanFactory::getBean('visp_vehicle', $vehicleId);

        if (empty($vehicle) || empty($vehicle->id)) {
            $GLOBALS['log']->fatal("VCHook DBG: Failed to instantiate visp_vehicle bean for ID: $vehicleId");
            return;
        }

        $GLOBALS['log']->fatal("VCHook DBG: Successfully loaded Vehicle: " . $vehicle->name);

        // 1. Verify exact fields available on the Parent Vehicle object
        $GLOBALS['log']->fatal("VCHook DBG: Vehicle Fields Available: " . print_r(array_keys($vehicle->field_defs), true));

        // Name population
        if (!empty($bean->date_of_check)) {
            $checkDate = new DateTime($bean->date_of_check);
            $bean->name = (!empty($vehicle->name) ? $vehicle->name : "Vehicle") . ' - ' . $checkDate->format('Y-m-d');
        }

        // 2. Set the data (We log these to see if the math/assignments are sound)
        $GLOBALS['log']->fatal("VCHook DBG: Assigning last check date: " . $bean->date_of_check . " and mileage: " . $bean->mileage);
        
        // NOTE: If your log output shows these fields end in _c, you must add _c here!
        $vehicle->date_last_checked = $bean->date_of_check;
        $vehicle->mileage_last_check = $bean->mileage;

        // Calculate service dates
        if (!empty($vehicle->date_last_service)) {
            $dateLastService = new DateTime($vehicle->date_last_service);
            $optionA = null; $optionB = null;

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
                } else {
                    $GLOBALS['log']->fatal("VCHook DBG: Run-rate math skipped. Miles Driven: $milesDriven, Days Elapsed: $daysElapsed");
                }
            }

            $finalNextServiceDate = null;
            if ($optionA && $optionB) { $finalNextServiceDate = ($optionA < $optionB) ? $optionA : $optionB; }
            elseif ($optionA) { $finalNextServiceDate = $optionA; }
            elseif ($optionB) { $finalNextServiceDate = $optionB; }

            if ($finalNextServiceDate) {
                $GLOBALS['log']->fatal("VCHook DBG: Calculated Next Service Date: " . $finalNextServiceDate->format('Y-m-d'));
                $vehicle->date_next_service = $finalNextServiceDate->format('Y-m-d');
            }
        } else {
            $GLOBALS['log']->fatal("VCHook DBG: date_last_service was empty on vehicle; skipping calculation.");
        }

        // 3. Attempt save
        $GLOBALS['log']->fatal("VCHook DBG: Triggering vehicle bean save...");
        $vehicle->save();
        $GLOBALS['log']->fatal("VCHook DBG: Vehicle bean save instruction finished.");
    }
}