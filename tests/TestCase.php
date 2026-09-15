<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roda antes do setUpTraits(), portanto antes do migrate:fresh do RefreshDatabase.
     *
     * Com a config cacheada (bootstrap/cache/config.php) o env DB_DATABASE=testing do
     * phpunit.xml é ignorado e a suíte apontaria para o banco de desenvolvimento,
     * que o RefreshDatabase apagaria. Aborta antes disso.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $database = $app['db']->connection()->getDatabaseName();

        if ($database !== 'testing') {
            throw new RuntimeException(
                "Os testes apontam para o banco [{$database}], não para [testing]. "
                . 'Rode `php artisan config:clear` (config cacheada ignora o DB_DATABASE do phpunit.xml) '
                . 'antes de executar a suíte, senão o RefreshDatabase apaga o banco de desenvolvimento.'
            );
        }

        return $app;
    }
}
