<?php

namespace App\Console\Commands\Accounting;

use App\Models\Tenant;
use App\Services\Accounting\Journal;
use Illuminate\Console\Command;

/** Re-posts the automatic journal entries of one office or all of them (manual entries are kept). */
class RebuildJournal extends Command
{
    protected $signature = 'rowad:accounting:rebuild {office? : tenant id; all offices when omitted}';

    protected $description = 'Re-post the journal entries of vouchers, owner-ledger entries and bank opening balances';

    public function handle(Journal $journal): int
    {
        $tenants = Tenant::query()->when($this->argument('office'), fn ($q, $id) => $q->whereKey($id))->get();
        foreach ($tenants as $tenant) {
            $count = $tenant->run(fn () => $journal->rebuild());
            $this->line("{$tenant->id}: {$count} ".__('قيد'));
        }

        return self::SUCCESS;
    }
}
