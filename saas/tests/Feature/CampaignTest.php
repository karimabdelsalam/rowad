<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use App\Models\NewsletterSubscriber;
use App\Models\Owner;
use App\Models\User;
use App\Services\Messaging\CampaignService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_email_campaign_to_owners_reaches_each_unique_address(): void
    {
        Owner::create(['first_name' => 'أ', 'email' => 'a@example.com']);
        Owner::create(['first_name' => 'ب', 'email' => 'a@example.com']); // duplicate address
        Owner::create(['first_name' => 'ج', 'email' => 'c@example.com']);
        Owner::create(['first_name' => 'د']);                            // no address
        $campaign = Campaign::create(['channel' => 'mail', 'audience' => 'owners', 'subject' => 'عرض', 'body' => '<p>مرحبا</p>', 'status' => 'draft']);

        $this->assertSame(2, app(CampaignService::class)->send($campaign));
        $campaign->refresh();
        $this->assertSame(['sent', 2, 2], [$campaign->status, $campaign->recipients_count, $campaign->sent_count]);

        $this->expectException(BusinessRuleException::class);
        app(CampaignService::class)->send($campaign);
    }

    public function test_send_action_from_the_campaigns_screen(): void
    {
        NewsletterSubscriber::create(['email' => 'list@example.com']);
        $campaign = Campaign::create(['channel' => 'mail', 'audience' => 'subscribers', 'subject' => 'نشرة', 'body' => 'x', 'status' => 'draft']);
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListCampaigns::class)->callAction(TestAction::make('send')->table($campaign))->assertNotified();

        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertSame('list@example.com', $campaign->messages()->sole()->recipient);
    }
}
