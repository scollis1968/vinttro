<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

// Registers POST URL: https://your-crm.com/Api/V8/custom/vinttro/v1/submit-form
$app->post('/vinttro/v1/submit-form', 'CustomApi\Controller\VinttroFormController:handleSubmit');