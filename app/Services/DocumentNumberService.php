<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Issues human-readable document numbers (IT-2026-000001).
 *
 * The actual reservation happens inside PKG_DOC_NUMBER so that concurrent web
 * requests cannot hand out the same number: Oracle serialises the counter update
 * in an autonomous transaction and releases the lock immediately.
 */
class DocumentNumberService
{
    public const TICKET      = 'ticket';
    public const DEVELOPMENT = 'development';
    public const TASK        = 'task';
    public const ASSET       = 'asset';

    /** Fallback prefixes when the company does not define its own. */
    private const PREFIXES = [
        self::TICKET      => 'IT',
        self::DEVELOPMENT => 'DEV',
        self::TASK        => 'TSK',
        self::ASSET       => 'AST',
    ];

    /**
     * Reserve the next number for a document kind within a company.
     *
     * @param  string  $kind  one of the class constants
     */
    public function next(string $kind, int $companyId, ?int $year = null): string
    {
        $prefix = $this->prefixFor($kind, $companyId);
        $year ??= (int) now()->format('Y');

        $row = DB::connection('oracle')->selectOne(
            'select PKG_DOC_NUMBER.NEXT_DOC_NO(:company_id, :prefix, :year_no) as doc_no from dual',
            ['company_id' => $companyId, 'prefix' => $prefix, 'year_no' => $year]
        );

        // Oracle returns the column as DOC_NO or doc_no depending on driver casing.
        $value = (array) $row;

        return (string) reset($value);
    }

    /**
     * Tickets use the company's configured ticket prefix (IT-2026-000001).
     * Other document kinds are prefixed with the company code as well
     * (MFG-DEV-2026-000001) so numbers stay readable and distinct when several
     * companies run side by side.
     */
    private function prefixFor(string $kind, int $companyId): string
    {
        $company = DB::connection('oracle')
            ->table('companies')
            ->select('code', 'ticket_prefix')
            ->where('id', $companyId)
            ->first();

        if ($kind === self::TICKET) {
            return strtoupper($company->ticket_prefix ?? self::PREFIXES[self::TICKET]);
        }

        $module = self::PREFIXES[$kind] ?? 'DOC';

        return $company === null
            ? $module
            : strtoupper($company->code) . '-' . $module;
    }
}
