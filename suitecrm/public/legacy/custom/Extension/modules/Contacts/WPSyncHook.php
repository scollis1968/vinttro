<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class WPSyncHook {
    public function syncToWordpress($bean, $event, $arguments) {
        // Write to a custom file in the /tmp directory
        $log_message = date('Y-m-d H:i:s') . " - VINTTRO HOOK TRIGGERED - Contact ID: " . $bean->id . "\n";
        file_put_contents('/tmp/vinttro_debug.log', $log_message, FILE_APPEND);        // This will appear in /var/log/nginx/error.log
        
        // 1. Only sync if the "Portal Active" checkbox is checked
        // Make sure 'portal_active_c' matches the field name in Studio
        if (empty($bean->portal_active_c)) {
            return;
        }

        // 2. Prepare the base member data
        $payload = [
            'email'             => $bean->email1,
            'first_name'        => $bean->first_name,
            'last_name'         => $bean->last_name,
            'membership_status' => $bean->membership_status_c, // Adjusted field name
            'vehicles'          => [] 
        ];

        // 3. Load the Many-to-Many Relationship
        // Use the relationship name you found in Studio
        $rel_name = 'visp_vehicle_contacts'; 
        
        if ($bean->load_relationship($rel_name)) {
            // Fetch all related vehicle beans
            $relatedVehicles = $bean->$rel_name->getBeans();

            foreach ($relatedVehicles as $vehicle) {
                $payload['vehicles'][] = [
                    'make'         => $vehicle->make_c,      // Custom fields usually end in _c
                    'model'        => $vehicle->model_c,
                    'reg'          => $vehicle->name,         // Often the 'Name' field is used for Reg
                    'next_service' => $vehicle->next_service_date_c,    
                    'mot_expiry'   => $vehicle->mot_date_c,
                    'ins_expiry'   => $vehicle->insurance_date_c,
                    'image_url'    => $this->getVehicleImageUrl($vehicle)
                ];
            }
        }

        // 4. Send the payload to WordPress
        $this->callWPAPI($payload);
    }

    private function getVehicleImageUrl($vehicle) {
        // If you are using a Photo field in SuiteCRM, the ID is stored in the field
        // Adjust 'photo_c' to your actual field name
        if (!empty($vehicle->photo_c)) {
            return "https://uatcrm.vinttro.co.uk/upload/" . $vehicle->photo_c;
        }
        return "https://vinttro.co.uk/wp-content/uploads/placeholder-car.png";
    }

    private function callWPAPI($payload) {
        $url = "https://vinttro.co.uk/wp-json/vinttro/v1/create-member";
        
        // Use your Application Password credentials
        $username = 'your_wp_admin_user';
        $app_password = 'xxxx xxxx xxxx xxxx xxxx xxxx';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$app_password")
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Debugging: Log the result to suitecrm.log if it fails
        if ($http_code !== 200 && $http_code !== 201) {
            $GLOBALS['log']->fatal("VINTTRO WP SYNC FAILED: Code $http_code - Response: $response");
        }
    }
}