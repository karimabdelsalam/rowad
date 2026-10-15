<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Executes every Eloquent relation of every tenant model against a real migrated
 * tenant database, so a typo in a table, foreign key or morph name fails here.
 */
class ModelSchemaIntegrityTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_every_relation_of_every_model_queries_successfully(): void
    {
        $this->initializeTenancy();
        $checked = 0;

        foreach (Relation::morphMap() as $alias => $class) {
            /** @var Model $model */
            $model = new $class;
            $model->getConnection()->table($model->getTable())->limit(1)->get();

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();
                if ($method->getNumberOfParameters() > 0 || ! str_starts_with((string) $method->getFileName(), app_path())
                    || ! $type instanceof ReflectionNamedType || ! is_subclass_of($type->getName(), Relation::class)) {
                    continue;
                }

                $relation = $model->{$method->getName()}();
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphTo) {
                    continue; // target table depends on the stored type
                }

                $relation->getQuery()->limit(1)->get();
                $checked++;
            }
        }

        $this->assertGreaterThan(100, $checked, "Only {$checked} relations were checked");
    }

    public function test_person_full_name_is_composed_from_four_parts(): void
    {
        $this->initializeTenancy();

        $owner = \App\Models\Owner::create([
            'first_name' => 'محمد', 'father_name' => 'عبدالله', 'grandfather_name' => 'سعد', 'family_name' => 'العتيبي',
        ]);

        $this->assertSame('محمد عبدالله سعد العتيبي', $owner->full_name);
    }
}
