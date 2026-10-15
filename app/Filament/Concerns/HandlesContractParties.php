<?php

namespace App\Filament\Concerns;

use App\Exceptions\BusinessRuleException;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Shared by contract create/edit pages: split repeater data and surface business errors. */
trait HandlesContractParties
{
    /** @return array{0: array<string, mixed>, 1: array<int, float>, 2: list<int>} */
    protected function splitContractData(array $data): array
    {
        $parties = collect($data['parties'] ?? [])
            ->filter(fn ($row) => filled($row['client_id'] ?? null))
            ->mapWithKeys(fn ($row) => [(int) $row['client_id'] => (float) $row['share_percent']])
            ->all();
        $terms = array_map('intval', $data['terms'] ?? []);
        unset($data['parties'], $data['terms']);

        return [$data, $parties, $terms];
    }

    protected function partiesForForm(Model $contract): array
    {
        return $contract->clients()->get()
            ->map(fn ($client) => ['client_id' => $client->id, 'share_percent' => (float) $client->pivot->share_percent])
            ->all();
    }

    protected function guardBusinessRules(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();

            throw new Halt;
        }
    }
}
