<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Pages\NumberingSettings;
use App\Filament\Admin\Resources\OfficeFiles\Pages\ManageOfficeFiles;
use App\Models\OfficeFile;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Legacy home/serial (numbering) and home/upload (file cloud). */
class OfficeToolsTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function user(string $role, string $type = 'staff'): User
    {
        $user = User::create(['name' => $role, 'email' => "{$role}@office.test", 'password' => 'secret-password', 'type' => $type]);
        if ($type === 'staff') {
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_numbering_continues_from_the_chosen_number_and_never_goes_back(): void
    {
        $numbers = app(DocumentNumberGenerator::class);
        $year = (int) now()->format('Y');
        $numbers->next('receipt_voucher');
        $this->assertSame(2, $numbers->upcoming('receipt_voucher'));

        $this->actingAs($this->user('admin'));
        Livewire::test(NumberingSettings::class)
            ->assertSet('data.receipt_voucher', 2)
            ->set('data.receipt_voucher', 500)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(sprintf('RV-%d-00500', $year), $numbers->next('receipt_voucher'));

        $this->expectException(BusinessRuleException::class);
        $numbers->startAt('receipt_voucher', 10);
    }

    public function test_file_cloud_is_for_staff_only(): void
    {
        $file = OfficeFile::create(['title' => 'عقد نموذجي']);
        $file->addMediaFromString('hello')->usingFileName('sample.txt')->toMediaCollection('file');
        $path = parse_url($file->url(), PHP_URL_PATH);

        $this->actingAs($this->user('admin'));
        Livewire::test(ManageOfficeFiles::class)->assertCanSeeTableRecords([$file]);
        $this->get($this->host.$path)->assertOk();

        $this->actingAs($this->user('owner-user', 'owner'));
        $this->get($this->host.$path)->assertNotFound();

        $this->actingAs($this->user('agent'));
        $this->get($this->host.parse_url(ManageOfficeFiles::getUrl(panel: 'admin'), PHP_URL_PATH))->assertForbidden();
    }
}
