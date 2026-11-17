<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class VinttroFormProcessor
{
    /**
     * Centralized execution method for converting forms/emails into unified entities.
     */
    public function processSubmission(array $data): string
    {
        // 1. Idempotency Check (Prevents Phase 2 email processing from creating a duplicate)
        $existingLeadId = $this->findLeadByGuid($data['submission_guid']);
        if ($existingLeadId) {
            return $existingLeadId; // Exit early safely; record is already present
        }

        // 2. Initialize Core Lead Record
        /** @var Lead $lead */
        $lead = \BeanFactory::newBean('Leads');
        $lead->first_name        = $data['lead_data']['first_name'] ?? '';
        $lead->last_name         = $data['lead_data']['last_name'] ?? '';
        $lead->email1            = $data['lead_data']['email1'] ?? '';
        $lead->submission_guid_c = $data['submission_guid']; // Custom Studio text field
        $lead->save();

        $leadId = $lead->id;

        // 3. Point 1: Handle Logged-In User Association
        if (!empty($data['wordpress_contact_id'])) {
            $contactId = $this->matchContact($data['wordpress_contact_id'], $lead->email1);
            if ($contactId) {
                $lead->load_relationship('contacts');
                $lead->contacts->add($contactId);
            }
        }

        // 4. Point 3: Complex Data Mapping (Vehicles Loop)
        // Find the vehicles loop inside your file and update it with this validation check:
        if (!empty($data['vehicles']) && is_array($data['vehicles'])) {
            foreach ($data['vehicles'] as $vehicleData) {
                $vehicle = \BeanFactory::newBean('visp_vehicle'); 
                
                // Defensive Verification Block
                if (!$vehicle) {
                    throw new \Exception("SuiteCRM Module 'v_Vehicles' could not be initialized. Please check that the singular module key name matches your Studio configurations exactly.");
                }
                
                $vehicle->name        = ($vehicleData['make'] ?? '') . ' ' . ($vehicleData['model'] ?? '');
                $vehicle->make        = $vehicleData['make'] ?? '';
                $vehicle->model       = $vehicleData['model'] ?? '';
                $vehicle->parent_id   = $leadId;
                $vehicle->parent_type = 'Leads';
                $vehicle->save();
            }
        }
        // 5. Point 2: Base64 Decoded Attachments Handling
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $file) {
                $this->saveBinaryAttachment($leadId, $file['filename'], $file['filedata']);
            }
        }

        return $leadId;
    }

    private function findLeadByGuid(string $guid): ?string
    {
        $db = \DBManagerFactory::getInstance();
        $query = "SELECT id FROM leads_cstm WHERE submission_guid_c = '" . $db->quote($guid) . "'";
        $result = $db->query($query);
        if ($row = $db->fetchByAssoc($result)) {
            return $row['id'];
        }
        return null;
    }

    private function matchContact($wpUid, $email): ?string
    {
        // Custom lookups to identify your SuiteCRM Contacts go here
        // E.g., matching a custom WordPress User ID field or fallback lookup via email address string
        return null; 
    }

    private function saveBinaryAttachment(string $parentId, string $filename, string $base64Data): void
    {
        /** @var Note $note */
        $note = \BeanFactory::newBean('Notes');
        $note->name        = $filename;
        $note->parent_type = 'Leads';
        $note->parent_id   = $parentId;
        $note->filename    = $filename;
        $note->file_mime_type = 'application/octet-stream'; // Extended dynamically if needed
        $note->save();

        // SuiteCRM physical upload folders save binary items raw, named precisely after their Note ID
        $uploadDir = \UploadStream::getSuhosinStatus() ? 'upload://' : 'upload/';
        $destinationFile = $uploadDir . $note->id;
        
        file_put_contents($destinationFile, base64_decode($base64Data));
    }
}