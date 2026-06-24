<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
// Require the logger utility
require_once 'custom/include/Vinttro/VinttroLogger.php';

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
            VinttroLogger::fatal("VCHook DBG: beforeSave running for existing link. Vehicle ID: $vehicleId");
            $vehicle = BeanFactory::getBean('visp_vehicle', $vehicleId);
            
            $this->runSyncLogic($bean, $vehicle);

            // FIX 2: Actually save the vehicle changes in beforeSave context
            try {
                self::$preventRecursion = true;
                $vehicle->save();
                VinttroLogger::fatal("VCHook DBG: beforeSave successfully synced and saved vehicle.");
            } catch (Exception $e) {
                VinttroLogger::fatal("VCHook DBG: Save exception in beforeSave vehicle save: " . $e->getMessage());
            } finally {
                self::$preventRecursion = false;
            }
        }
    }

    // INTERCEPTOR 2: Handles the subpanel creation (First Save)
    public function afterRelationshipAddMethod($bean, $event, $arguments)
    {
        if (self::$preventRecursion) return;

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
                VinttroLogger::fatal("VCHook DBG: afterRelationshipAdd triggered. Vehicle: $vehicleId, Check: $checkId");
                
                $checkBean = BeanFactory::getBean('visp_vehicle_check', $checkId);
                $vehicleBean = BeanFactory::getBean('visp_vehicle', $vehicleId);

                if (!empty($checkBean) && !empty($vehicleBean)) {
                    $this->runSyncLogic($checkBean, $vehicleBean);
                    
                    try {
                        self::$preventRecursion = true;
                        $checkBean->save();
                        $vehicleBean->save();
                        VinttroLogger::fatal("VCHook DBG: Subpanel first-save automation successfully synced.");
                    } catch (Exception $e) {
                        VinttroLogger::fatal("VCHook DBG: Save exception in relationship hook: " . $e->getMessage());
                    } finally {
                        self::$preventRecursion = false;
                    }
                }
            }
        }
    }

    // CORE AUTOMATION ENGINE
    private function runSyncLogic($bean, $vehicle)
    {
        $timedate = TimeDate::getInstance();

        // 1. Automatically populate the name of the check record safely
        if (!empty($bean->date_of_check)) {
            $normalizedCheckDate = $timedate->to_db_date($bean->date_of_check, false);
            if ($normalizedCheckDate) {
                $bean->name = (!empty($vehicle->name) ? $vehicle->name : "Vehicle") . ' - ' . $normalizedCheckDate;
            }
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

        // FIX 1: Normalize both dates into YYYY-MM-DD string format before comparing alphabetically
        $newCheckDateDb = $timedate->to_db_date($newCheckDate, false);
        $currentVehicleDateDb = $timedate->to_db_date($currentVehicleDate, false);

        $shouldUpdateVehicle = false;
        if ($newCheckMileage > 0 && !empty($newCheckDateDb)) {
            if (empty($currentVehicleDateDb) || strpos($currentVehicleDateDb, '0000-00-00') !== false || $newCheckDateDb >= $currentVehicleDateDb) {
                $shouldUpdateVehicle = true;
            }
        }

        if ($shouldUpdateVehicle) {
            $setVField('date_last_check', $newCheckDateDb);
            $setVField('mileage_last_check', $newCheckMileage);

            try {
                $vDateLastService = $getVField('date_last_service');
                $vServiceIntervalMonths = $getVField('service_interval_months');
                $vMileageLastService = $getVField('mileage_last_service');
                $vServiceIntervalMiles = $getVField('service_interval_miles');

                $vDateLastServiceDb = $timedate->to_db_date($vDateLastService, false);

                if (!empty($vDateLastServiceDb) && strpos($vDateLastServiceDb, '0000-00-00') === false) {
                    $dateLastService = new DateTime($vDateLastServiceDb);
                    $optionA = null; 
                    $optionB = null;

                    if (!empty($vServiceIntervalMonths) && (int)$vServiceIntervalMonths > 0) {
                        $optionA = clone $dateLastService;
                        $months = (int)$vServiceIntervalMonths;
                        $optionA->modify("+$months months");
                    }

                    if (!empty($newCheckDateDb) && !empty($vMileageLastService)) {
                        $dateOfCheck = new DateTime($newCheckDateDb);
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
                VinttroLogger:: fatal("VCHook DBG: Handled exception during math execution: " . $dateEx->getMessage());
            }
        }
    }
}