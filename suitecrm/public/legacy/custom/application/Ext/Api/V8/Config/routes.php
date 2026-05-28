<?php
if (!defined('sugarEntry') || !sugarEntry) { die('Not A Valid Entry Point'); }

$app->post('/vinttro/v1/submit-form', 'CustomApi\Controller\VinttroFormController:handleSubmit');