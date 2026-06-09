<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VehicleCheckHook 
{
    private static $preventRecursion = false;

    public function beforeSaveMethod($bean, $event, $arguments) 
    {
        if (self::$preventRecursion) {
            return;
        }

        $GLOBALS['log']->fatal("VCHook DBG: Hook triggered for Check Name: " . $bean->name);

        $linkName = 'visp_vehicle_visp_vehicle_check'; 

        if (!$bean->load_relationship($linkName)) {
            $GLOBALS['log']->fatal("VCHook DBG: Failed to load relationship link: $linkName");
            return;
        }

        $vehicleId = '';
        $relatedIds = $bean->$linkName->get();
        if (!empty($relatedIds) && is_array($relatedIds)) {
            $vehicleId = reset($relatedIds);
        }

        // Fallback for subpanel creation requests
        if (empty($vehicleId)) {
            if (!empty($_REQUEST['relate_id']) && isset($_REQUEST['relate_to']) && $_REQUEST['relate_to'] == 'visp_vehicle') {
                $vehicleId = $_REQUEST['relate_id'];
            } elseif (!empty($_REQUEST['parent_id']) && isset($_REQUEST['parent_type']) && $_REQUEST['parent_type'] == 'visp_vehicle') {
                $vehicleId = $_REQUEST['parent_id'];
            }
        }

        if (empty($vehicleId)) {
            $GLOBALS['log']->fatal("VCHook DBG: Abandoning hook. No parent Vehicle ID could be resolved.");
            return; 
        }

        $vehicle = BeanFactory::getBean('visp_vehicle', $vehicleId);
        if (empty($vehicle) || empty($vehicle->id)) {
            $GLOBALS['log']->fatal("VCHook DBG: Failed to instantiate vehicle object for ID: $vehicleId");
            return;
        }

        // --- FIELD SCANNER DEBUG LOG ---
        // This will print every field containing keywords to help you catch naming typos instantly
        $matchedFields = [];
        foreach (array_keys($vehicle->field_defs) as $fName) {
            if (preg_match('/(check|mile|service)/i', $fName)) {
                $matchedFields[] = $fName;
            }
        }
        $GLOBALS['log']->fatal("VCHook DBG: Actual Vehicle Fields Available: " . implode(', ', $matchedFields));


        // 1. Automatically populate the name of the check record
        if (!empty($bean->date_of_check)) {
            $checkDate = new DateTime($bean->date_of_check);
            $bean->name = (!empty($vehicle->name) ? $vehicle->name : "Vehicle") . ' - ' . $checkDate->format('Y-m-d');
        }

        // Robust, warning-safe closures to read/write properties dynamically
        $getVField = function($fieldName) use ($vehicle) {
            if (isset($vehicle->field_defs[$fieldName . '_c'])) {
                return $vehicle->{$fieldName . '_c'};
            }
            if (isset($vehicle->field_defs[$fieldName])) {
                return $vehicle->{$fieldName};
            }
            return null; 
        };

        $setVField = function($fieldName, $value) use ($vehicle) {
            if (isset($vehicle->field_defs[$fieldName . '_c'])) {
                $vehicle->{$fieldName . '_c'} = $value;
                return true;
            }
            if (isset($vehicle->field_defs[$fieldName])) {
                $vehicle->{$fieldName} = $value;
                return true;
            }
            // Fallback: assign to base property if definition wasn't found
            $vehicle->{$fieldName} = $value;
            return false;
        };

        // Gather existing vehicle metrics
        $currentVehicleDate = $getVField('date_last_checked');
        $newCheckMileage = (float)$bean->mileage;
        $newCheckDate = $bean->date_of_check;

        // --- USER RULE CONDITIONAL CHECK ---
        // Always update if mileage > 0 AND (vehicle date is empty OR the new check date is equal/greater)
        $shouldUpdateVehicle = false;
        if ($newCheckMileage > 0) {
            if (empty($currentVehicleDate) || strpos($currentVehicleDate, '0000-00-00') !== false || $newCheckDate >= $currentVehicleDate) {
                $shouldUpdateVehicle = true;
            } else {
                $GLOBALS['log']->fatal("VCHook DBG: Vehicle update skipped. Incoming check date ($newCheckDate) is older than vehicle's last check date ($currentVehicleDate).");
            }
        } else {
            $GLOBALS['log']->fatal("VCHook DBG: Vehicle update skipped. Mileage must be greater than 0. Provided: $newCheckMileage");
        }

        if ($shouldUpdateVehicle) {
            $setVField('date_last_checked', $newCheckDate);
            $setVField('mileage_last_check', $newCheckMileage);
            $GLOBALS['log']->fatal("VCHook DBG: Staged Last Check Date ($newCheckDate) and Mileage ($newCheckMileage) onto Vehicle.");

            // 3. Calculate and update service dates inside a protected bubble
            try {
                $vDateLastService = $getVField('date_last_service');
                $vServiceIntervalsMonths = $getVField('service_intervals_months');
                $vMilesLastService = $getVField('miles_last_service');
                $vServiceIntervalMiles = $getVField('service_interval_miles');

                if (!empty($vDateLastService) && strpos($vDateLastService, '0000-00-00') === false) {
                    $dateLastService = new DateTime($vDateLastService);
                    $optionA = null; 
                    $optionB = null;

                    if (!empty($vServiceIntervalsMonths) && (int)$vServiceIntervalsMonths > 0) {
                        $optionA = clone $dateLastService;
                        $months = (int)$vServiceIntervalsMonths;
                        $optionA->modify("+$months months");
                    }

                    if (!empty($newCheckDate) && !empty($vMilesLastService)) {
                        $dateOfCheck = new DateTime($newCheckDate);
                        $milesDriven = $newCheckMileage - (float)$vMilesLastService;
                        $daysElapsed = $dateLastService->diff($dateOfCheck)->days;
                        if ($dateOfCheck < $dateLastService) { $daysElapsed = -$daysElapsed; }

                        if ($milesDriven > 0 && $daysElapsed > 0 && (float)$vServiceIntervalMiles > 0) {
                            $totalDaysAllowed = (float)$vServiceIntervalMiles * ($daysElapsed / $milesDriven);
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
                        $nextServiceString = $finalNextServiceDate->format('Y-m-d');
                        $setVField('date_next_service', $nextServiceString);
                        $GLOBALS['log']->fatal("VCHook DBG: Staged Next Service Date calculation onto Vehicle: " . $nextServiceString);
                    }
                } else {
                    $GLOBALS['log']->fatal("VCHook DBG: Vehicle date_last_service is empty or invalid. Skipping date math.");
                }
            } catch (Exception $dateEx) {
                // If the math breaks, we log it but do NOT crash the script
                $GLOBALS['log']->fatal("VCHook DBG: Warning handled during date math execution: " . $dateEx->getMessage());
            }
        }

        // 4. Safe Save Execution (Guaranteed to commit base properties regardless of math success)
        try {
            self::$preventRecursion = true;
            $GLOBALS['log']->fatal("VCHook DBG: Saving vehicle record now...");
            $vehicle->save();
            $GLOBALS['log']->fatal("VCHook DBG: Vehicle record saved successfully.");
        } catch (Exception $e) {
            $GLOBALS['log']->fatal("VCHook DBG: EXCEPTION CAUGHT during vehicle save: " . $e->getMessage());
        } finally {
            self::$preventRecursion = false;
        }
    }
}