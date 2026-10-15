<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Things `php artisan optimize` needs in production (docs/deployment.md). */
class DeploymentReadinessTest extends TestCase
{
    public function test_route_names_are_unique_so_routes_can_be_cached(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())->map->getName()->filter();

        $this->assertSame([], $names->duplicates()->values()->all());
    }

    public function test_env_is_only_read_inside_config_files(): void
    {
        $offenders = collect(File::allFiles([app_path(), database_path(), base_path('routes')]))
            ->filter(fn ($file) => $file->getExtension() === 'php' && preg_match('/\benv\(/', $file->getContents()))
            ->map(fn ($file) => str_replace(base_path().'/', '', $file->getPathname()))
            ->values()->all();

        $this->assertSame([], $offenders, 'env() returns null once config is cached; read it through config/*.php');
    }
}
