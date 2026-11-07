<?php
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VinttroEmailHandler {
    public function processEmailAttachments($bean, $event, $arguments) {
        // Only run for brand-new imports (avoids infinite loops)
        if ($arguments['isUpdate']) return;

        $GLOBALS['log']->fatal("Vinttro Hook: Processing Email " . $bean->id);

        // Load the relationship to find attachments (stored as Notes)
        $bean->load_relationship('notes');
        $attachments = $bean->notes->getBeans();

        foreach ($attachments as $note) {
            // Check if it's a PDF
            if (strtolower(pathinfo($note->filename, PATHINFO_EXTENSION)) === 'pdf') {
                $file_path = "upload/" . $note->id;
                
                if (file_exists($file_path)) {
                    $GLOBALS['log']->fatal("Vinttro Hook: Found PDF " . $note->filename);
                    $this->sendToVinttroApi($file_path, $note, $bean);
                }
            }
        }
    }

    private function sendToVinttroApi($path, $note, $emailBean) {
        // Your actual processing logic goes here
        // 1. POST the file to your API
        // 2. API returns Client ID
        // 3. Update the $note->parent_id and $note->parent_type to link it
        
        $GLOBALS['log']->fatal("Vinttro Hook: Sending file to API...");
    }
}

