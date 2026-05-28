<?php
use Psr\Container\ContainerInterface as Container;

// Using __DIR__ guarantees the server finds the file without crashing
require_once __DIR__ . '/Controller/VinttroFormController.php';

return [
    'CustomApi\Controller\VinttroFormController' => function (Container $container) {
        return new \CustomApi\Controller\VinttroFormController();
    }
];