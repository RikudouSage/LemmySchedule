<?php declare(strict_types=1);

use App\Kernel;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeConsumer;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool)$_SERVER['APP_DEBUG']);
$kernel->boot();

// Return the Bref consumer service
return $kernel->getContainer()->get(EventBridgeConsumer::class);
