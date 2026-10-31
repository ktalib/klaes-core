<?php

namespace Tests\Unit;

use App\Services\Sltr\SltrTotGenerator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SLTR special case: Sales Agreement + CofO and no Transfer of Title -> a generated one,
 * Party 1 = the SA buyer, Party 2 = the name on the CofO, dated with the CofO.
 * plan() only reads, so it runs against real records (sqlsrv is read-only in tests).
 */
class SltrTotGeneratorTest extends TestCase
{
    private function generator(): SltrTotGenerator
    {
        return app(SltrTotGenerator::class);
    }

    /** An SLTR file the generator would act on, from the newest Sales Agreements. */
    private function candidate(): ?array
    {
        $files = DB::connection('sqlsrv')->table('file_history_staging')
            ->where('transaction_type', 'Sale Agreement')->where('mlsFNo', 'like', 'SLTR-%')
            ->orderByDesc('id')->limit(40)->pluck('mlsFNo');
        foreach ($files as $fileNumber) {
            $plan = $this->generator()->plan($fileNumber);
            if ($plan['action'] === 'generate') {
                return [$fileNumber, $plan];
            }
        }

        return null;
    }

    public function test_only_sltr_files(): void
    {
        $this->assertTrue(SltrTotGenerator::appliesTo('SLTR-91811'));
        $this->assertFalse(SltrTotGenerator::appliesTo('RES-1981-308'));
        $this->assertSame('not an SLTR file', $this->generator()->plan('RES-1981-308')['reason']);
    }

    public function test_plans_the_transfer_from_the_sa_buyer_to_the_cofo_holder(): void
    {
        $found = $this->candidate();
        if (!$found) {
            $this->markTestSkipped('No SLTR file awaiting a generated Transfer of Title among the newest.');
        }
        [$fileNumber, $plan] = $found;
        $db = DB::connection('sqlsrv');

        [$saleTable, $saleId] = explode(':', $plan['sale']);
        $sale = $db->table($saleTable)->where('id', $saleId)->first();
        [$cofoTable, $cofoId] = explode(':', $plan['cofo']);
        $cofo = $db->table($cofoTable)->where('id', $cofoId)->first();
        $row = $plan['row'];

        $this->assertSame('Transfer of Title', $row['transaction_type']);
        $this->assertSame(SltrTotGenerator::SOURCE, $row['source']);
        // The comment is what marks it "System generated" in the File History Summary.
        $this->assertMatchesRegularExpression('/system generated/i', $row['comments']);
        // A Sale Agreement, never a Deed of Assignment.
        $this->assertMatchesRegularExpression('/^\s*(sales? agreement|agreement of sale)\s*$/i', $sale->transaction_type);
        $this->assertSame(trim($sale->party_2 ?: $sale->Grantee), $row['party_1']);
        $this->assertSame(trim($cofo->Grantee ?: $cofo->party_2), $row['party_2']);
        $this->assertSame($row['party_1'], $row['Grantor']);
        $this->assertSame($row['party_2'], $row['Grantee']);
        // No date: the real transaction date is not known.
        $this->assertNull($row['transaction_date']);
        $this->assertNull($row['reg_date']);
        $this->assertSame(strtoupper($fileNumber), strtoupper($row['fileno']));

        // No Sale Agreement on the file is dated after the one used.
        foreach (['file_history_staging', 'pra'] as $table) {
            $newer = $db->table($table)
                ->whereRaw("UPPER(LTRIM(RTRIM(transaction_type))) IN ('SALE AGREEMENT','SALES AGREEMENT','AGREEMENT OF SALE')")
                ->where(fn ($q) => $q->where('fileno', $fileNumber)->orWhere('mlsFNo', $fileNumber))
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->whereNull('deleted_at')
                ->where('transaction_date', '>', $sale->transaction_date)
                ->exists();
            $this->assertFalse($newer, "a later agreement in {$table}");
        }
    }

    public function test_a_file_with_a_transfer_of_title_is_left_alone(): void
    {
        $fileNumber = DB::connection('sqlsrv')->table('file_history_staging')
            ->where('transaction_type', 'like', '%transfer of title%')
            ->where('mlsFNo', 'like', 'SLTR-%')
            ->value('mlsFNo');
        if (!$fileNumber) {
            $this->markTestSkipped('No SLTR file with a Transfer of Title yet.');
        }
        $this->assertSame('skip', $this->generator()->plan($fileNumber)['action']);
    }
}
