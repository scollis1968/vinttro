<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$app->post('/vinttro/v1/submit-form', function ($request, $response) {
    // 1. Grab the parsed JSON payload
    $params = $request->getParsedBody();

    // 2. Validate essential parameters
    if (empty($params['submission_guid'])) {
        return $response->withJson(['status' => 'error', 'message' => 'Missing submission_guid'], 400);
    }

    // 3. Construct an absolute path to our isolated Service Layer file
    $processorPath = dirname(__DIR__, 5) . '/include/Vinttro/VinttroFormProcessor.php';
    
    if (!file_exists($processorPath)) {
        return $response->withJson([
            'status' => 'error', 
            'message' => 'Processor configuration file missing at: ' . $processorPath
        ], 500);
    }

    require_once $processorPath;

    try {
        // 4. Instantiate our standalone worker class and process data
        $processor = new \VinttroFormProcessor();
        $leadId = $processor->processSubmission($params);

        return $response->withJson([
            'status'  => 'success',
            'message' => 'Form submission processed successfully',
            'lead_id' => $leadId
        ], 200);

    } catch (\Throwable $e) {
        // Capture any processing errors safely as structured JSON data
        return $response->withJson([
            'status'  => 'error',
            'message' => 'Processing Failure: ' . $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine()
        ], 500);
    }
});