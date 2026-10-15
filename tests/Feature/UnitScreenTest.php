<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Units\Pages\CreateUnit;
use App\Filament\Admin\Resources\Units\Pages\EditUnit;
use App\Models\CustomFieldGroup;
use App\Models\Lookups\MeterType;
use App\Models\Lookups\PropertyCategory;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class UnitScreenTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        Filament::setCurrentPanel('admin');
        $user = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_unit_is_created_with_owner_shares_meters_and_custom_fields(): void
    {
        [$a, $b] = [Owner::create(['first_name' => 'أحمد']), Owner::create(['first_name' => 'سارة'])];
        $category = PropertyCategory::where('title', 'شقة')->first();
        $field = CustomFieldGroup::create(['title' => 'مزايا', 'property_category_id' => $category->id])
            ->fields()->create(['title' => 'موقف سيارة', 'type' => 'checkbox']);
        $meter = MeterType::where('title', 'كهرباء')->first();

        Livewire::test(CreateUnit::class)
            ->fillForm([
                'title' => 'شقة 5 - برج النخيل',
                'property_category_id' => $category->id,
                'offer_type' => 'rent',
                'status' => 'available',
                'calendar' => 'gregorian',
                'owner_shares' => [
                    ['owner_id' => $a->id, 'share_percent' => 75, 'expenses_percent' => 75],
                    ['owner_id' => $b->id, 'share_percent' => 25, 'expenses_percent' => 25],
                ],
                'meters' => [['meter_type_id' => $meter->id, 'number' => 'E-9981']],
                'custom_fields' => [$field->id => true],
                'rent_amount' => 45000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $unit = Unit::sole();
        $this->assertEquals([75, 25], $unit->owners()->orderBy('owners.id')->get()->pluck('pivot.share_percent')->map(fn ($v) => (float) $v)->all());
        $this->assertSame('E-9981', $unit->meters()->sole()->number);
        $this->assertSame('1', $unit->customFieldValues()->sole()->value);

        $state = Livewire::test(EditUnit::class, ['record' => $unit->getRouteKey()])->instance()->form->getRawState();
        $this->assertEquals([
            ['owner_id' => $a->id, 'share_percent' => 75.0, 'expenses_percent' => 75.0],
            ['owner_id' => $b->id, 'share_percent' => 25.0, 'expenses_percent' => 25.0],
        ], array_values($state['owner_shares']));
    }

    public function test_owner_shares_must_total_100(): void
    {
        $owner = Owner::create(['first_name' => 'أحمد']);

        Livewire::test(CreateUnit::class)
            ->fillForm([
                'title' => 'محل 3', 'offer_type' => 'rent', 'status' => 'available', 'calendar' => 'gregorian',
                'owner_shares' => [['owner_id' => $owner->id, 'share_percent' => 60, 'expenses_percent' => 60]],
            ])
            ->call('create')
            ->assertNotified('مجموع نسب الملكية يجب أن يساوي 100%');

        $this->assertSame(0, Unit::count());
    }
}
