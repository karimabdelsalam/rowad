<?php

namespace Tests\Feature;

use App\Models\ContractTerm;
use App\Models\OfficeFile;
use App\Models\Owner;
use App\Models\User;
use App\Services\Portal\PortalAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Findings of the security review (docs/decisions.md): each one stays fixed. */
class SecurityHardeningTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
        URL::forceRootUrl($this->host);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function staff(string $role): User
    {
        $user = User::create(['name' => $role, 'email' => "{$role}@office.test", 'password' => 'secret-password']);
        $user->assignRole($role);

        return $user;
    }

    public function test_printed_documents_escape_names_and_sanitise_terms(): void
    {
        $contract = $this->makeRentContract();
        $contract->clients()->first()->update(['first_name' => '<script>alert(1)</script>']);
        $term = ContractTerm::create(['title' => 'بند', 'content' => "سطر أول\n<img src=x onerror=alert(2)>"]);
        $contract->terms()->attach($term);
        $contract->update(['extra_terms' => '<p onclick="alert(3)">شرط</p><script>alert(4)</script>']);

        $html = $this->actingAs($this->staff('admin'))->get("{$this->host}/print/rent_contract/{$contract->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('onclick="alert', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)', $html);
        $this->assertStringContainsString('<p>شرط</p>', $html);
    }

    public function test_uploaded_files_that_could_run_scripts_are_downloaded_not_rendered(): void
    {
        $file = OfficeFile::create(['title' => 'svg']);
        $file->addMediaFromString('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')->usingFileName('x.svg')->toMediaCollection('file');
        $image = OfficeFile::create(['title' => 'pdf']);
        $image->addMediaFromString('%PDF-1.4 test')->usingFileName('x.pdf')->toMediaCollection('file');

        $this->actingAs($this->staff('admin'));
        $svg = $this->get($file->url())->assertOk();
        $this->assertStringContainsString('attachment', (string) $svg->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $svg->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $svg->headers->get('Content-Security-Policy'));

        $pdf = $this->get($image->url())->assertOk();
        $this->assertStringNotContainsString('attachment', (string) $pdf->headers->get('Content-Disposition'));
    }

    public function test_impersonation_links_need_the_login_as_permission_even_when_signed(): void
    {
        $owner = Owner::create(['first_name' => 'مالك']);
        app(PortalAccounts::class)->issue($owner, 'owner@office.test', 'chosen-pass-9');
        $user = $owner->fresh()->user;
        $url = URL::temporarySignedRoute('portal.impersonate', now()->addMinutes(2), ['user' => $user->id, 'panel' => 'owner']);

        $this->actingAs($this->staff('viewer'))->get($url)->assertForbidden();
        $this->assertFalse(auth()->user()->is($user));
    }

    public function test_pages_carry_security_headers(): void
    {
        $response = $this->get('http://localhost/');

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
