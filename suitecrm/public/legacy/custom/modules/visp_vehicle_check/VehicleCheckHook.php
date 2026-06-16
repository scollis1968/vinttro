<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VehicleCheckHook 
{
    private static $preventRecursion = false;

    // INTERCEPTOR 1: Handles updates and manual second saves cleanly
    public function beforeSaveMethod($bean, $event, $arguments) 
    {
        if (self::$preventRecursion) return;

        $linkName = 'visp_vehicle_visp_vehicle_check'; 
        if (!$bean->load_relationship($linkName)) return;

        $vehicleId = '';
        $relatedIds = $bean->$linkName->get();
        if (!empty($relatedIds) && is_array($relatedIds)) {
            $vehicleId = reset($relatedIds);
        }

        if (!empty($vehicleId)) {
            $GLOBALS['log']->fatal("VCHook DBG: beforeSave running for existing link. Vehicle ID: $vehicleId");
            $vehicle = BeanFactory::getBean('visp_vehicle', $vehicleId);
            $this->runSyncLogic($bean, $vehicle);
        }
    }

    // INTERCEPTOR 2: Handles the subpanel creation (First Save)
    public function afterRelationshipAddMethod($bean, $event, $arguments)
    {
        if (self::$preventRecursion) return;

        // Ensure we are working with the correct relationship link layout
        if (isset($arguments['link']) && $arguments['link'] === 'visp_vehicle_visp_vehicle_check') {
            $vehicleId = '';
            $checkId = '';

            if ($arguments['module'] === 'visp_vehicle') {
                $vehicleId = $arguments['id'];
                $checkId = $arguments['related_id'];
            } elseif ($arguments['module'] === 'visp_vehicle_check') {
                $checkId = $arguments['id'];
                $vehicleId = $arguments['related_id'];
            }

            if (!empty($vehicleId) && !empty($checkId)) {
                $GLOBALS['log']->fatal("VCHook DBG: afterRelationshipAdd triggered. Vehicle: $vehicleId, Check: $checkId");
                
                $checkBean = BeanFactory::getBean('visp_vehicle_check', $checkId);
                $vehicleBean = BeanFactory::getBean('visp_vehicle', $vehicleId);

                if (!empty($checkBean) && !empty($vehicleBean)) {
                    $this->runSyncLogic($checkBean, $vehicleBean);
                    
                    // Since this runs AFTER the check bean save, we manually save changes made to both records.
                    try {
                        self::$preventRecursion = true;
                        $checkBean->save();
                        $vehicleBean->save();
                        $GLOBALS['log']->fatal("VCHook DBG: Subpanel first-save automation successfully synced.");
                    } catch (Exception $e) {
                        $GLOBALS['log']->fatal("VCHook DBG: Save exception in relationship hook: " . $e->getMessage());
                    } finally {
                        self::$preventRecursion = false;
                    }
                }
            }
        }
    }

    // CORE AUTOMATION ENGINE: Contains your mathematical calculations and naming logic
    private function runSyncLogic($bean, $vehicle)
    {
        // 1. Automatically populate the name of the check record
        if (!empty($bean->date_of_check)) {
            $checkDate = new DateTime($bean->date_of_check);
            $bean->name = (!empty($vehicle->name) ? $vehicle->name : "Vehicle") . ' - ' . $checkDate->format('Y-m-d');
        }

        $getVField = function($fieldName) use ($vehicle) {
            if (isset($vehicle->field_defs[$fieldName . '_c'])) return $vehicle->{$fieldName . '_c'};
            if (isset($vehicle->field_defs[$fieldName])) return $vehicle->{$fieldName};
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
            $vehicle->{$fieldName} = $value;
            return false;
        };

        $currentVehicleDate = $getVField('date_last_check');
        $newCheckMileage = (float)$bean->mileage;
        $newCheckDate = $bean->date_of_check;

        $shouldUpdateVehicle = false;
        if ($newCheckMileage > 0) {
            if (empty($currentVehicleDate) || strpos($currentVehicleDate, '0000-00-00') !== false || $newCheckDate >= $currentVehicleDate) {
                $shouldUpdateVehicle = true;
            }
        }

        if ($shouldUpdateVehicle) {
            $setVField('date_last_check', $newCheckDate);
            $setVField('mileage_last_check', $newCheckMileage);

            try {
                $vDateLastService = $getVField('date_last_service');
                $vServiceIntervalMonths = $getVField('service_interval_months');
                $vMileageLastService = $getVField('mileage_last_service');
                $vServiceIntervalMiles = $getVField('service_interval_miles');

                if (!empty($vDateLastService) && strpos($vDateLastService, '0000-00-00') === false) {
                    $dateLastService = new DateTime($vDateLastService);
                    $optionA = null; 
                    $optionB = null;

                    if (!empty($vServiceIntervalMonths) && (int)$vServiceIntervalMonths > 0) {
                        $optionA = clone $dateLastService;
                        $months = (int)$vServiceIntervalMonths;
                        $optionA->modify("+$months months");
                    }

                    if (!empty($newCheckDate) && !empty($vMileageLastService)) {
                        $dateOfCheck = new DateTime($newCheckDate);
                        $milesDriven = $newCheckMileage - (float)$vMileageLastService;
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
                        $setVField('date_next_service', $finalNextServiceDate->format('Y-m-d'));
                    }
                }
            } catch (Exception $dateEx) {
                $GLOBALS['log']->fatal("VCHook DBG: Handled exception during math execution: " . $dateEx->getMessage());
            }
        }
    }
}