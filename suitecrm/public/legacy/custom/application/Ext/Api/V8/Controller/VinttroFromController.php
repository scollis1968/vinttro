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
        // Retrieve the parsed JSON payload sent by WordPress
        $params = $request->getParsedBody();

        // Basic sanity check
        if (empty($params['submission_guid'])) {
            return $response->withJson(['status' => 'error', 'message' => 'Missing submission_guid'], 400);
        }

        // Initialize the centralized Service class
        require_once 'custom/include/Vinttro/VinttroFormProcessor.php';

        try {
            $processor = new \VinttroFormProcessor();
            $leadId = $processor->processSubmission($params);

            return $response->withJson([
                'status'  => 'success',
                'message' => 'Form submission processed completely',
                'lead_id' => $leadId
            ], 200);

        } catch (\Exception $e) {
            return $response->withJson([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}