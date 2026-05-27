<?php

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// --- ADD THIS LINE AT THE TOP ---
file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - File was included\n", FILE_APPEND);

class WPSyncHook {
    public function syncToWordpress($bean, $event, $arguments) {
        // --- ADD THIS LINE AT THE TOP ---
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 1 trigered\n", FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
        
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

        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 2 payload = " . json_encode($payload) . "\n" , FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
        // 3. Load the Many-to-Many Relationship
        // Use the relationship name you found in Studio
        $rel_name = 'visp_vehicle_contacts'; 
        
        if ($bean->load_relationship($rel_name)) {
            // Fetch all related vehicle beans
            $relatedVehicles = $bean->$rel_name->getBeans();

            foreach ($relatedVehicles as $vehicle) {
                $payload['vehicles'][] = [
                    'make'         => $vehicle->make,      // Custom fields usually end in _c
                    'model'        => $vehicle->model,
                    'reg'          => $vehicle->name,         // Assuming 'name' field holds the registration number
                    'mot_expiry'   => $vehicle->date_mot,
                    'next_service' => $vehicle->date_service,
                    'ins_expiry'   => $vehicle->date_registered,
                    'image_url'    => $this->getVehicleImageUrl($vehicle)
                ];
            }
        }

        // 4. Send the payload to WordPress
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 3 payload = " . print_r($payload, true) . "\n" , FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
        $this->callWPAPI($payload);
        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - syncToWordPress 4 All done! \n" , FILE_APPEND);        // 1. Only sync if the "Portal Active" checkbox is checked
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
        $url = "https://uat.vinttro.co.uk/wp-json/vinttro/v1/create-member/";

        // Use your Application Password credentials
        $username = 'vinttro_admin';
        $app_password = '6YSN vLuS Pa4d kMIk flMY TYoQ';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        // Tell PHP to follow 301/302 redirects automatically
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Since you are on UAT/Self-Signed sometimes:
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$app_password")
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Catch CURL errors (like DNS or connection timeouts)
        if(curl_errno($ch)) {
            $error_msg = curl_error($ch);
            file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - CURL ERROR: $error_msg\n", FILE_APPEND);
        }

        curl_close($ch);

        file_put_contents('/tmp/vinttro_file_load.log', date('Y-m-d H:i:s') . " - WP Response Code: $http_code | Response: $response\n", FILE_APPEND);
    }
}