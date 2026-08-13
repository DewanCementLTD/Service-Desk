<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

/**
 * Narrows the ticket categories offered to users down to ERP and Network, which
 * is what the business actually raises tickets against.
 *
 * The retired categories are deactivated rather than deleted: tickets already
 * filed against them keep a readable label, and an administrator can bring any
 * of them back from the lookups screen without a release. Re-running this is
 * safe — it only ever sets the two flags.
 */
class LookupTrimSeeder extends Seeder
{
    /** code => the name users should see. */
    private const KEEP = [
        'ERP'     => 'ERP',
        'NETWORK' => 'Network',
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::KEEP as $code => $name) {
            TicketCategory::where('code', $code)->update([
                'name'       => $name,
                'is_active'  => 1,
                'sort_order' => $sort += 10,
            ]);
        }

        $retired = TicketCategory::whereNotIn('code', array_keys(self::KEEP))
            ->where('is_active', 1)
            ->update(['is_active' => 0]);

        $this->command?->info("Categories: kept " . count(self::KEEP) . ", deactivated {$retired}.");
    }
}
