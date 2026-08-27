<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum solo activa la sesión "stateful" (cookie httpOnly) para
        // requests con Referer/Origin de un dominio en SANCTUM_STATEFUL_DOMAINS
        // — así se comporta el navegador real desde el SPA Angular; el cliente
        // de test no manda ese header por defecto, así que se fija aquí.
        $this->withHeader('Referer', config('app.frontend_url'));
    }
}
