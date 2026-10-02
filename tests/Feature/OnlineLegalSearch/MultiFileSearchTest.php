<?php

namespace Tests\Feature\OnlineLegalSearch;

use App\Http\Controllers\OnlineLegalSearch\OnlineLsDashboardController;
use App\Models\LegalSearchOnlinePayment;
use App\Models\LegalSearchOnlineRequest;
use App\Models\LegalSearchOnlineVerification;
use App\Models\OnlineLsSearchPurpose;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * One Online Legal Search request covering several files.
 *
 * The price is unit × file count, and each file becomes its own approval row so
 * it gets its own report and can be approved independently. The two things worth
 * proving here are that the pricing is decided by the SERVER and that a
 * multi-file payment really does open one request per file.
 *
 * Skipped automatically when the portal's sqlsrv connection is unreachable.
 */
class MultiFileSearchTest extends TestCase
{
    private const FILE_A = 'RES-2026-MULTI-A';
    private const FILE_B = 'RES-2026-MULTI-B';
    private const FILE_C = 'RES-2026-MULTI-C';

    private const UNIT = OnlineLsDashboardController::PAYMENT_AMOUNT_KOBO;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            LegalSearchOnlinePayment::query()->limit(1)->exists();
        } catch (\Throwable $e) {
            $this->markTestSkipped('The sqlsrv connection used by the Online Legal Search portal is not reachable.');
        }
    }

    protected function tearDown(): void
    {
        // The dev database is shared with live UI testing: remove only this run's
        // own rows, never a blanket predicate.
        foreach ([self::FILE_A, self::FILE_B, self::FILE_C] as $file) {
            LegalSearchOnlineVerification::where('file_number', $file)->delete();
        }

        foreach (LegalSearchOnlinePayment::whereIn('file_number', [self::FILE_A])->get() as $payment) {
            LegalSearchOnlineRequest::where('payment_id', $payment->id)->delete();
            $payment->delete();
        }

        parent::tearDown();
    }

    /** Put a verified identification in the session so the payment gate opens. */
    private function verifyIdentificationFor(string $fileNumber): void
    {
        $token = (string) \Illuminate\Support\Str::uuid();

        LegalSearchOnlineVerification::create([
            'file_number'            => $fileNumber,
            'requester_email'        => 'multi@example.com',
            'session_token'          => $token,
            'customer_type'          => 'individual',
            'applicant_full_name'    => 'Iorkua Kator Daniel',
            'applicant_phone'        => '08031234567',
            'applicant_address'      => '12 Ahmadu Bello Way, Kano',
            'identification_type'    => 'nin',
            'id_front_path'          => 'test/front.jpg',
            'id_name_match_score'    => 100,
            'id_verification_status' => LegalSearchOnlineVerification::STATUS_VERIFIED,
            'id_verified_at'         => now(),
        ]);

        $this->withSession(['ols_verification_token' => $token]);
    }

    // ---- Pricing ------------------------------------------------------------

    public function test_the_payment_page_prices_a_single_file_at_the_unit_amount(): void
    {
        $this->get(route('ols.result', ['query' => self::FILE_A]))
            ->assertOk()
            ->assertSessionHas('ols_search_amount', self::UNIT)
            ->assertSessionHas('ols_search_files', [self::FILE_A]);
    }

    public function test_the_payment_page_multiplies_the_price_by_the_file_count(): void
    {
        $response = $this->get(route('ols.result', [
            'query' => self::FILE_A,
            'files' => [self::FILE_B, self::FILE_C],
        ]));

        $response->assertOk()
            ->assertSessionHas('ols_search_amount', self::UNIT * 3)
            ->assertSessionHas('ols_search_files', [self::FILE_A, self::FILE_B, self::FILE_C]);

        // The applicant must be able to check the exact set they are paying for.
        $response->assertSee(self::FILE_B)->assertSee(self::FILE_C);
    }

    /** Nobody should be charged twice for the same file. */
    public function test_duplicate_file_numbers_are_charged_once(): void
    {
        $this->get(route('ols.result', [
            'query' => self::FILE_A,
            // Same file again, and again in a different case.
            'files' => [self::FILE_A, strtolower(self::FILE_A), self::FILE_B],
        ]))
            ->assertOk()
            ->assertSessionHas('ols_search_amount', self::UNIT * 2)
            ->assertSessionHas('ols_search_files', [self::FILE_A, self::FILE_B]);
    }

    /** The cap is applied server-side, not trusted from the browser. */
    public function test_the_basket_is_capped(): void
    {
        $many = [];
        for ($i = 0; $i < 40; $i++) {
            $many[] = 'RES-2026-MULTI-' . $i;
        }

        $this->get(route('ols.result', ['query' => self::FILE_A, 'files' => $many]));

        $this->assertCount(
            OnlineLsDashboardController::MAX_FILES_PER_REQUEST,
            session('ols_search_files')
        );
    }

    // ---- Payment ------------------------------------------------------------

    /** A multi-file payment opens one approval request per file. */
    public function test_a_multi_file_payment_opens_one_request_per_file(): void
    {
        $purpose = OnlineLsSearchPurpose::active()->first();

        if (!$purpose) {
            $this->markTestSkipped('No active Online Legal Search purpose is configured in this database.');
        }

        $this->verifyIdentificationFor(self::FILE_A);

        // Price the basket the way the payment page does.
        $this->get(route('ols.result', [
            'query' => self::FILE_A,
            'files' => [self::FILE_B, self::FILE_C],
        ]))->assertOk();

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data'   => [
                    'status'   => 'success',
                    'amount'   => self::UNIT * 3,
                    'customer' => ['email' => 'multi@example.com'],
                ],
            ], 200),
        ]);

        $reference = 'test-multi-' . uniqid();

        $this->postJson(route('ols.payment.verify'), [
            'reference'   => $reference,
            'email'       => 'multi@example.com',
            'purpose_id'  => $purpose->id,
            'file_number' => self::FILE_A,
        ])->assertOk()->assertJson(['success' => true, 'file_count' => 3]);

        $payment = LegalSearchOnlinePayment::where('reference', $reference)->firstOrFail();

        $this->assertSame(3, (int) $payment->file_count);
        $this->assertSame([self::FILE_A, self::FILE_B, self::FILE_C], $payment->fileNumbers());

        // One request per file, all under the same payment.
        $requests = LegalSearchOnlineRequest::where('payment_id', $payment->id)->get();

        $this->assertCount(3, $requests);
        $this->assertEqualsCanonicalizing(
            [self::FILE_A, self::FILE_B, self::FILE_C],
            $requests->pluck('file_number')->all()
        );

        // Each carries its own request number — they are separately approvable.
        $this->assertCount(3, $requests->pluck('request_no')->filter()->unique());
    }

    /**
     * The pricing backstop: a charge that does not cover the basket is refused
     * rather than quietly honoured.
     */
    public function test_a_payment_below_the_basket_price_is_refused(): void
    {
        $purpose = OnlineLsSearchPurpose::active()->first();

        if (!$purpose) {
            $this->markTestSkipped('No active Online Legal Search purpose is configured in this database.');
        }

        $this->verifyIdentificationFor(self::FILE_A);

        $this->get(route('ols.result', [
            'query' => self::FILE_A,
            'files' => [self::FILE_B, self::FILE_C],
        ]))->assertOk();

        // Paystack reports a single-file charge against a three-file basket.
        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data'   => [
                    'status'   => 'success',
                    'amount'   => self::UNIT,
                    'customer' => ['email' => 'multi@example.com'],
                ],
            ], 200),
        ]);

        $reference = 'test-multi-underpaid-' . uniqid();

        $this->postJson(route('ols.payment.verify'), [
            'reference'   => $reference,
            'email'       => 'multi@example.com',
            'purpose_id'  => $purpose->id,
            'file_number' => self::FILE_A,
        ])->assertStatus(422);

        $this->assertNull(
            LegalSearchOnlinePayment::where('reference', $reference)->first(),
            'An underpaid basket must not produce a payment row.'
        );
    }

    /** Rows written before multi-file existed still resolve to one file. */
    public function test_a_legacy_single_file_payment_still_resolves(): void
    {
        $payment = new LegalSearchOnlinePayment(['file_number' => self::FILE_A]);

        $this->assertSame([self::FILE_A], $payment->fileNumbers());
        $this->assertFalse($payment->isMultiFile());
    }
}
