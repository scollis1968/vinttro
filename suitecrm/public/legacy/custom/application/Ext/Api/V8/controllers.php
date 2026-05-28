<?php
use Psr\Container\ContainerInterface as Container;

// Manually require the controller file to keep it a clean drop-in solution
require_once 'custom/application/Ext/Api/V8/Controller/VinttroFormController.php';

return [
    'CustomApi\Controller\VinttroFormController' => function (Container $container) {
        return new \CustomApi\Controller\VinttroFormController();
    }
];