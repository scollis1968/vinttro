<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VehicleCheckHook 
{
    private static $preventRecursion = false;

    public function beforeSaveMethod($bean, $event, $arguments) 
    {
        if (self::$preventRecursion) {
            $GLOBALS['log']->fatal("VCHook DBG: Recursion prevented. Skipping nested save cycle.");
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
            $GLOBALS['log']->fatal("VCHook DBG: Found Vehicle ID via relationship framework: $vehicleId");
        }

        // Fallback for new records created directly from a vehicle subpanel
        if (empty($vehicleId)) {
            if (!empty($_REQUEST['relate_id']) && isset($_REQUEST['relate_to']) && $_REQUEST['relate_to'] == 'visp_vehicle') {
                $vehicleId = $_REQUEST['relate_id'];
                $GLOBALS['log']->fatal("VCHook DBG: Found Vehicle ID via subpanel relate_id REQUEST: $vehicleId");
            } elseif (!empty($_REQUEST['parent_id']) && isset($_REQUEST['parent_type']) && $_REQUEST['parent_type'] == 'visp_vehicle') {
                $vehicleId = $_REQUEST['parent_id'];
                $GLOBALS['log']->fatal("VCHook DBG: Found Vehicle ID via subpanel parent_id REQUEST: $vehicleId");
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

        // 1. Automatically populate the name of the check
        if (!empty($bean->date_of_check)) {
            $checkDate = new DateTime($bean->date_of_check);
            $bean->name = (!empty($vehicle->name) ? $vehicle->name : "Vehicle") . ' - ' . $checkDate->format('Y-m-d');
            $GLOBALS['log']->fatal("VCHook DBG: Populated check record name to: " . $bean->name);
        }

        // Closures to map custom studio fields safely regardless of '_c' status
        $setVField = function($fieldName, $value) use ($vehicle) {
            if (isset($vehicle->field_defs[$fieldName . '_c'])) {
                $vehicle->{$fieldName . '_c'} = $value;
            } else {
                $vehicle->{$fieldName} = $value;
            }
        };

        $getVField = function($fieldName) use ($vehicle) {
            if (isset($vehicle->field_defs[$fieldName . '_c'])) {
                return $vehicle->{$fieldName . '_c'};
            }
            return $vehicle->{$fieldName};
        };

        // 2. Set base check data on parent vehicle
        $setVField('date_last_checked', $bean->date_of_check);
        $setVField('mileage_last_check', $bean->mileage);
        $GLOBALS['log']->fatal("VCHook DBG: Staged Last Check Date (" . $bean->date_of_check . ") and Mileage (" . $bean->mileage . ") onto Vehicle.");

        // 3. Calculate and update service dates inside a protected environment
        try {
            $vDateLastService = $getVField('date_last_service');
            $vServiceIntervalsMonths = $getVField('service_intervals_months');
            $vMilesLastService = $getVField('miles_last_service');
            $vServiceIntervalMiles = $getVField('service_interval_miles');

            // Defensive Check: Ensure date exists and isn't a database placeholder '0000-00-00...'
            if (!empty($vDateLastService) && strpos($vDateLastService, '0000-00-00') === false) {
                $dateLastService = new DateTime($vDateLastService);
                $optionA = null; 
                $optionB = null;

                if (!empty($vServiceIntervalsMonths) && (int)$vServiceIntervalsMonths > 0) {
                    $optionA = clone $dateLastService;
                    $months = (int)$vServiceIntervalsMonths;
                    $optionA->modify("+$months months");
                }

                if (!empty($bean->date_of_check) && !empty($bean->mileage) && !empty($vMilesLastService)) {
                    $dateOfCheck = new DateTime($bean->date_of_check);
                    $milesDriven = (float)$bean->mileage - (float)$vMilesLastService;
                    $daysElapsed = $dateLastService->diff($dateOfCheck)->days;
                    if ($dateOfCheck < $dateLastService) { $daysElapsed = -$daysElapsed; }

                    if ($milesDriven > 0 && $daysElapsed > 0 && (float)$vServiceIntervalMiles > 0) {
                        $totalDaysAllowed = (float)$vServiceIntervalMiles * ($daysElapsed / $milesDriven);
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
                    $nextServiceString = $finalNextServiceDate->format('Y-m-d');
                    $setVField('date_next_service', $nextServiceString);
                    $GLOBALS['log']->fatal("VCHook DBG: Staged Next Service Date calculation onto Vehicle: " . $nextServiceString);
                }
            } else {
                $GLOBALS['log']->fatal("VCHook DBG: Vehicle date_last_service is empty or default zero-date; skipping timeline calculations.");
            }
        } catch (Exception $dateEx) {
            $GLOBALS['log']->fatal("VCHook DBG: CRITICAL error during date parsing/math: " . $dateEx->getMessage());
        }

        // 4. Safe Save Execution (Guaranteed to execute even if date math is bypassed)
        try {
            self::$preventRecursion = true;
            $GLOBALS['log']->fatal("VCHook DBG: Saving vehicle record now...");
            $vehicle->save();
            $GLOBALS['log']->fatal("VCHook DBG: Vehicle record saved successfully.");
        } catch (Exception $e) {
            $GLOBALS['log']->fatal("VCHook DBG: EXCEPTION CAUGHT during vehicle save processing: " . $e->getMessage());
        } finally {
            self::$preventRecursion = false;
        }
    }
}