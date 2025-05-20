<?php

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use SensitiveParameter;

final readonly class DoctrineSqliteMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends Middleware\AbstractDriverMiddleware {
            public function connect(#[SensitiveParameter] array $params): Driver\Connection
            {
                $connection = parent::connect($params);

                $connection->exec('PRAGMA foreign_keys=ON');

                return $connection;
            }
        };
    }
}
