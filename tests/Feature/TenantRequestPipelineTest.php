<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyIfNotCentral;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

/**
 * Regression: Livewire update requests (login form, every panel action) only run the 'web' group
 * before the panel's persistent middleware. The office database must be selected before the
 * session and the user are resolved, or they are read from the platform database.
 */
class TenantRequestPipelineTest extends TestCase
{
    public function test_office_is_identified_before_the_session_starts(): void
    {
        $web = app('router')->getMiddlewareGroups()['web'];

        $this->assertSame(InitializeTenancyIfNotCentral::class, $web[0]);
        $this->assertLessThan(array_search(StartSession::class, $web, true), array_search(InitializeTenancyIfNotCentral::class, $web, true));
    }
}
