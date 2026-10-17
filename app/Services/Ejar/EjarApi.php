<?php

namespace App\Services\Ejar;

use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Ejar External Contract Registration Service (ECRS), JSON:API over HTTPS with Basic authentication using the
 * office's own API key and secret (issued by Ejar to registered brokerage offices). One instance per office:
 * the credentials come from the office's settings.
 */
class EjarApi
{
    public const DEFAULT_BASE_URL = 'https://ejar.sa/api/v1/ecrs';

    /** Our ID types → Ejar's party_id_type / entity_type. */
    public const ID_TYPES = ['national_id' => 'national_id', 'iqama' => 'resident_id', 'passport' => 'passport', 'gcc_id' => 'gcc_id'];

    public function __construct(private ?string $key = null, private ?string $secret = null, private ?string $baseUrl = null)
    {
        $this->key ??= Settings::get('ejar_api_key');
        $this->secret ??= Settings::get('ejar_api_secret');
        $this->baseUrl ??= Settings::get('ejar_base_url') ?: self::DEFAULT_BASE_URL;
    }

    public static function connected(): bool
    {
        return Settings::enabled('ejar_enabled') && filled(Settings::get('ejar_api_key')) && filled(Settings::get('ejar_api_secret'));
    }

    /** Checks the credentials: Ejar answers 200 to a valid key pair, 401/403 otherwise. */
    public function ping(): void
    {
        $this->send('get', '/contracts', ['page' => 1]);
    }

    // ---------------------------------------------------------------- contracts

    /** @return array{id: string, contract_number: ?string, version: ?int} */
    public function createContract(string $start, string $end, string $type = 'residential'): array
    {
        $contract = $this->send('post', '/contracts', ['data' => ['contract' => [
            'contract_start_date' => $start, 'contract_end_date' => $end, 'contract_type' => $type,
        ]]])['data']['contract'] ?? [];

        return ['id' => (string) ($contract['id'] ?? ''), 'contract_number' => $contract['contract_number'] ?? null, 'version' => isset($contract['version']) ? (int) $contract['version'] : null];
    }

    /** Puts the (single) Ejar unit of an Ejar property on the contract; returns the contract unit id Ejar assigned. */
    public function setUnit(string $contractId, string $propertyId, string $unitId, ?string $unitType = null): ?string
    {
        $json = $this->send('post', "/contracts/{$contractId}/units", ['data' => ['contract_property' => [
            'id' => $propertyId,
            'contract_units' => [array_filter(['id' => $unitId, 'unit_type' => $unitType])],
        ]]]);

        return ($id = data_get($json, 'data.relationships.contract_units.0.id')) !== null ? (string) $id : null;
    }

    /** @param list<array{party_type: string, party_role: string, party_data: array<string, string>}> $parties */
    public function addParties(string $contractId, array $parties): array
    {
        return $this->send('post', "/contracts/{$contractId}/party", ['data' => ['contract_parties' => $parties]]);
    }

    /** @param list<array<string, mixed>> $services */
    public function setUnitServices(string $contractId, array $services): array
    {
        return $this->send('post', "/contracts/{$contractId}/unit_services", ['data' => ['contract_unit_services' => $services]]);
    }

    public function submit(string $contractId): array
    {
        return $this->send('post', "/contracts/{$contractId}/submit", ['data' => new \stdClass]);
    }

    /** Archives an unsubmitted draft (Ejar keeps it readable but it can no longer be edited or submitted). */
    public function archive(string $contractId): void
    {
        $this->send('delete', "/contracts/{$contractId}");
    }

    /** @return array{id: ?string, state: ?string} */
    public function status(string $contractNumber, ?int $version = null): array
    {
        $contract = $this->send('get', '/contracts', ['filters' => array_filter(['contract_number' => $contractNumber, 'contract_version' => $version])])['data']['contract'] ?? [];

        return ['id' => $contract['id'] ?? null, 'state' => $contract['state'] ?? null];
    }

    /** The contract as Ejar prints it (PDF bytes). */
    public function pdf(string $contractId): string
    {
        return $this->request()->accept('application/pdf')->get($this->url("/contracts/{$contractId}/download_pdf"))
            ->throwIf(fn (Response $r) => ! $r->successful(), fn (Response $r) => $this->failure($r))->body();
    }

    // ---------------------------------------------------------------- entities, properties, units

    /** A person as Ejar knows them (verifies the ID before a contract uses it). */
    public function individual(string $idNumber, string $idType): array
    {
        return $this->send('get', '/individual_entities', ['filters' => ['id_number' => $idNumber, 'entity_type' => self::ID_TYPES[$idType] ?? $idType]])['data'] ?? [];
    }

    public function organization(string $crNumber, string $registrationDate): array
    {
        return $this->send('get', '/organization_entities', ['filters' => ['cr_number' => $crNumber, 'registration_date' => $registrationDate]])['data'] ?? [];
    }

    /** Properties registered in Ejar under an ownership document (deed number + issue date, Gregorian). @return list<array> */
    public function properties(string $documentNumber, string $issuedDate): array
    {
        return array_values(array_map(fn ($p) => ($p['attributes'] ?? []) + ['id' => $p['id'] ?? ($p['attributes']['id'] ?? null)],
            $this->send('get', '/properties', ['data' => ['document_number' => $documentNumber, 'issued_date' => $issuedDate]])['data'] ?? []));
    }

    /** @return list<array> */
    public function units(string $propertyId, ?string $unitNumber = null): array
    {
        return array_values(array_map(fn ($u) => ($u['attributes'] ?? []) + ['id' => $u['id'] ?? ($u['attributes']['id'] ?? null)],
            $this->send('get', "/properties/{$propertyId}/units", array_filter(['data' => $unitNumber ? ['unit_number' => $unitNumber] : null]))['data'] ?? []));
    }

    // ---------------------------------------------------------------- transport

    private function request(): PendingRequest
    {
        $token = base64_encode($this->key.':'.$this->secret);

        // the specification shows both header names; Ejar accepts the standard one
        return Http::withHeaders(['Authorization' => 'Basic '.$token, 'Authentication' => 'Basic '.$token])
            ->acceptJson()->timeout(30)->retry(2, 800, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->baseUrl, '/').$path;
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $payload = []): array
    {
        if (blank($this->key) || blank($this->secret)) {
            throw new EjarException(__('أدخل مفتاح وسر الربط مع إيجار من صفحة "منصة إيجار" أولًا.'));
        }
        try {
            $request = $this->request();
            $response = match ($method) {
                'get' => $request->get($this->url($path), $payload),
                'delete' => $request->delete($this->url($path)),
                default => $request->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE), 'application/json; charset=utf-8')->post($this->url($path)),
            };
        } catch (ConnectionException) {
            throw new EjarException(__('تعذّر الوصول إلى منصة إيجار. حاول بعد قليل.'));
        }
        if (! $response->successful()) {
            throw $this->failure($response);
        }

        return (array) $response->json();
    }

    private function failure(Response $response): EjarException
    {
        $messages = collect($response->json('errors') ?? [])->map(fn ($e) => is_array($e)
            ? (data_get($e, 'detail.ar') ?: data_get($e, 'detail.en') ?: ($e['title'] ?? null)).(isset($e['code']) ? " ({$e['code']})" : '')
            : (string) $e)->filter()->values()->all();

        return new EjarException(match (true) {
            in_array($response->status(), [401, 403], true) => __('رفضت منصة إيجار مفتاح الربط. تأكد من المفتاح والسر، ومن تفعيل الربط لمكتبك لدى فريق إيجار.'),
            $response->status() === 404 => __('لم تجد منصة إيجار البيانات المطلوبة.'),
            default => __('رفضت منصة إيجار الطلب.'),
        }, $messages, $response->status());
    }
}
