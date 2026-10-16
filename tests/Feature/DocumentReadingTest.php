<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Clients\Pages\CreateClient;
use App\Filament\Admin\Resources\Units\Pages\CreateUnit;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\DocumentReader;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Filling forms from a photo of an ID card or a title deed, read by the AI service. */
class DocumentReadingTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private User $admin;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        config(['rowad.ai.api_key' => 'test-key', 'rowad.ai.model' => 'test-model']);
        $this->admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function aiAnswers(array $json): void
    {
        Http::fake(['api.anthropic.com/*' => function (Request $request) use ($json) {
            $this->sent[] = $request->data();

            return Http::response(['content' => [['type' => 'text', 'text' => "```json\n".json_encode($json, JSON_UNESCAPED_UNICODE)."\n```"]],
                'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 1500, 'output_tokens' => 120]]);
        }]);
    }

    public function test_an_id_card_fills_the_client_form(): void
    {
        $this->aiAnswers(['document_type' => 'national_id', 'first_name' => 'خالد', 'father_name' => 'عبدالله', 'grandfather_name' => 'سعد',
            'family_name' => 'الحربي', 'id_number' => '١٠٤٣٨٨٧٩٦٣', 'nationality' => null, 'birth_date' => null, 'birth_date_hijri' => '1405-03-12',
            'issue_place' => 'الرياض']);

        $component = Livewire::test(CreateClient::class)
            ->callAction(TestAction::make('readId')->schemaComponent('identity'), ['document' => UploadedFile::fake()->image('id.jpg', 800, 500)])
            ->assertNotified()
            ->assertSchemaStateSet([
                'first_name' => 'خالد', 'father_name' => 'عبدالله', 'grandfather_name' => 'سعد', 'family_name' => 'الحربي',
                'id_type' => 'national_id', 'id_number' => '1043887963', 'nationality' => 'سعودي', 'id_source' => 'الرياض',
            ]);
        // the card shows the Hijri birth date only; it is converted (Umm al-Qura) for the Gregorian field
        $this->assertStringStartsWith(\App\Support\Hijri::parse('1405-03-12')->format('Y-m-d'), (string) $component->get('data.birth_date'));

        $request = $this->sent[0];
        $this->assertSame('image', $request['messages'][0]['content'][0]['type']);
        $this->assertSame('image/jpeg', $request['messages'][0]['content'][0]['source']['media_type']);
        $this->assertSame(1, AiMessage::where('role', 'assistant')->where('kind', 'reading')->count(), 'counts as one assistant question');
    }

    public function test_a_deed_fills_plan_plot_area_and_adds_the_deed(): void
    {
        $this->aiAnswers(['deed_number' => '310105040123', 'deed_date_hijri' => '1440-07-15', 'issuer' => 'كتابة العدل الأولى بالرياض',
            'owner_name' => 'سعد العتيبي', 'city' => 'الرياض', 'district' => 'الملقا', 'plan_number' => '1120', 'plot_number' => '58', 'area' => 625]);

        $component = Livewire::test(CreateUnit::class)
            ->callAction(TestAction::make('readDeed')->schemaComponent('deedReader'), ['document' => UploadedFile::fake()->create('deed.pdf', 200, 'application/pdf')])
            ->assertSchemaStateSet(['plan_number' => '1120', 'plot_number' => '58', 'area' => '625']);

        $deeds = array_values($component->get('data.deeds'));
        $this->assertSame('310105040123', $deeds[0]['number']);
        $this->assertSame('1440-07-15', $deeds[0]['issue_date']);
        $this->assertSame('document', $this->sent[0]['messages'][0]['content'][0]['type'], 'PDFs go as documents');
    }

    public function test_unreadable_answers_and_missing_ai_are_reported_not_filled(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Sorry, the image is blurry.']], 'stop_reason' => 'end_turn'])]);
        try {
            app(DocumentReader::class)->read($this->admin, 'id', (string) UploadedFile::fake()->image('x.jpg')->get(), 'image/jpeg');
            $this->fail('expected an error');
        } catch (AiUnavailable $e) {
            $this->assertStringContainsString('تعذّرت قراءة المستند', $e->getMessage());
        }

        config(['rowad.ai.api_key' => null]);
        Livewire::test(CreateClient::class)
            ->callAction(TestAction::make('readId')->schemaComponent('identity'), ['document' => UploadedFile::fake()->image('id.jpg')])
            ->assertNotified()
            ->assertSchemaStateSet(['first_name' => null]);
    }
}
