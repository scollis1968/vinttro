<?php
namespace CustomApi\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class VinttroFormController extends BaseController
{
    public function handleSubmit(Request $request, Response $response)
    {
        $params = $request->getParsedBody();

        if (empty($params['submission_guid'])) {
            return $response->withJson(['status' => 'error', 'message' => 'Missing submission_guid'], 400);
        }

        // Absolute path construction independent of webserver rewrite configurations
        $processorPath = dirname(__DIR__, 5) . '/include/Vinttro/VinttroFormProcessor.php';
        
        if (!file_exists($processorPath)) {
            return $response->withJson([
                'status' => 'error', 
                'message' => 'Processor file not found at path: ' . $processorPath
            ], 500);
        }

        require_once $processorPath;

        try {
            $processor = new \VinttroFormProcessor();
            $leadId = $processor->processSubmission($params);

            return $response->withJson([
                'status'  => 'success',
                'message' => 'Form submission processed completely',
                'lead_id' => $leadId
            ], 200);

        } catch (\Throwable $e) { 
            // Intercepts PHP Fatal Errors and reveals the exact culprit
            return $response->withJson([
                'status'  => 'error',
                'message' => 'PHP Fatal Error: ' . $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ], 500);
        }
    }
}