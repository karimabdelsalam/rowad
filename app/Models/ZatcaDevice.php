<?php

namespace App\Models;

use App\Services\Zatca\Phase2\Certificate;
use App\Services\Zatca\Phase2\FatooraApi;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** The office's e-invoicing unit (EGS) registered with ZATCA: its key, certificates and hash chain. */
#[Guarded(['id'])]
#[Hidden(['private_key', 'compliance_secret', 'production_secret'])]
class ZatcaDevice extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_COMPLIANCE = 'compliance';

    public const STATUS_READY = 'ready';

    public const STATUS_REVOKED = 'revoked';

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'compliance_secret' => 'encrypted',
            'production_secret' => 'encrypted',
            'certificate_expires_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'onboarding_log' => 'array',
            'counter' => 'integer',
        ];
    }

    public static function active(): ?self
    {
        return self::query()->where('status', self::STATUS_READY)->latest('id')->first();
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && filled($this->production_token);
    }

    public function api(): FatooraApi
    {
        return new FatooraApi($this->environment);
    }

    /**
     * The developer portal answers every production-CSID request with the same sample certificate (someone else's key),
     * so documents signed there can never verify. In that test environment the unit keeps its compliance certificate
     * and sends documents to the compliance-check endpoint, which validates them exactly like reporting/clearance.
     */
    public function isSandbox(): bool
    {
        return $this->environment === 'developer-portal';
    }

    /** The certificate invoices are signed with: production once issued, compliance during onboarding (and in the sandbox). */
    public function certificate(): Certificate
    {
        return Certificate::fromBinarySecurityToken($this->isSandbox() ? $this->compliance_token : ($this->production_token ?: $this->compliance_token));
    }

    /** @return array{0: string, 1: string} */
    public function productionCredentials(): array
    {
        return $this->isSandbox() ? $this->complianceCredentials() : [$this->production_token, $this->production_secret];
    }

    /** @return array{0: string, 1: string} */
    public function complianceCredentials(): array
    {
        return [$this->compliance_token, $this->compliance_secret];
    }

    public function environmentLabel(): string
    {
        return __(FatooraApi::ENVIRONMENTS[$this->environment] ?? $this->environment);
    }
}
