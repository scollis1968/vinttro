<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class ContactsDriverHook
{
    public function handleDriverRelation($bean, $event, $arguments)
    {
        // 1. Check if the "Is Driver" checkbox is checked
        if ($bean->is_driver_c != 1 && $bean->is_driver_c != '1') {
            return; // Exit early if unchecked
        }

        // 2. Define your exact 1-to-1 relationship name
        // 💡 NOTE: Change this to match your exact Studio relationship name (e.g., contacts_visp_driver_1)
        $relationshipName = 'contacts_visp_driver_1'; 

        if (!$bean->load_relationship($relationshipName)) {
            $GLOBALS['log']->error("ContactsDriverHook: Failed to load relationship '$relationshipName'");
            return;
        }

        // 3. Check if a Driver is already linked to this Contact
        $relatedDrivers = $bean->$relationshipName->getBeans();
        
        if (empty($relatedDrivers)) {
            // 4. No driver found -> Let's create a brand new one
            /** @var visp_driver $driver */
            $driver = \BeanFactory::newBean('visp_driver');
            
            // Auto-populate the Driver Name using the Contact's name details
            $contactName = trim($bean->first_name . ' ' . $bean->last_name);
            $driver->name = "Driver Info: " . (!empty($contactName) ? $contactName : $bean->id);
            
            // Assign the same owner/team for data consistency
            $driver->assigned_user_id = $bean->assigned_user_id;
            
            // Save the Driver record first to generate its unique ID
            $driver->save();

            // 5. Establish the 1-to-1 relationship link
            $bean->$relationshipName->add($driver->id);
            
            $GLOBALS['log']->info("ContactsDriverHook: Successfully created and linked visp_driver ID: {$driver->id} to Contact ID: {$bean->id}");
        }
    }
}