<?php

use App\Kernel;

ini_set('display_errors', 'On');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
