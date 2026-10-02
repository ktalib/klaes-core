<?php

namespace Tests\Feature\OnlineLegalSearch;

use App\Models\LegalSearchOnlineVerification;
use App\Services\Ocr\OcrException;
use App\Services\Ocr\OcrImagePreprocessor;
use App\Services\Ocr\OcrReader;
use App\Models\LegalSearchOnlinePayment;
use App\Models\LegalSearchOnlineRequest;
use App\Models\OnlineLsSearchPurpose;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Online Legal Search ID name verification endpoint.
 *
 * OCR is mocked throughout: the suite must not depend on a local Tesseract
 * installation, and the point of these tests is the surrounding workflow —
 * validation, private storage, the payment gate, and document authorization.
 * The matching logic itself is covered by
 * tests/Unit/Services/IdNameVerificationServiceTest.
 *
 * NOTE: these hit the sqlsrv connection the portal lives on. They are skipped
 * automatically when that connection is unreachable, so the suite stays green on
 * a workstation without the database.
 */
class IdVerificationTest extends TestCase
{
    private const FILE_NUMBER = 'RES-2026-TEST-1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipWithoutPortalDatabase();

        Storage::fake('ols_private');

        config([
            'id_verification.thresholds.verified' => 80,
            'id_verification.thresholds.review'   => 60,
            'id_verification.min_matching_parts'  => 2,
            'id_verification.store_raw_text'      => false,
            // GD preprocessing on a fake 10x10 image adds nothing here and would
            // only widen what these tests depend on.
            'id_verification.preprocess.enabled'  => false,
        ]);
    }

    protected function tearDown(): void
    {
        // The dev database is shared with live UI testing, so only the rows this
        // run created are removed - never a blanket predicate.
        LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->delete();

        foreach (LegalSearchOnlinePayment::where('file_number', self::FILE_NUMBER)->get() as $payment) {
            LegalSearchOnlineRequest::where('payment_id', $payment->id)->delete();
            $payment->delete();
        }

        parent::tearDown();
    }

    /** Skip rather than fail when the portal's sqlsrv connection is unavailable. */
    private function skipWithoutPortalDatabase(): void
    {
        try {
            LegalSearchOnlineVerification::query()->limit(1)->exists();
        } catch (\Throwable $e) {
            $this->markTestSkipped('The sqlsrv connection used by the Online Legal Search portal is not reachable.');
        }
    }

    /** Bind a stubbed OCR reader that returns fixed text. */
    private function fakeOcrReturning(string $text): void
    {
        $this->instance(OcrReader::class, new class($text) implements OcrReader {
            public function __construct(private string $text)
            {
            }

            public function text(string $absolutePath): string
            {
                return $this->text;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });

        $this->instance(OcrImagePreprocessor::class, new OcrImagePreprocessor());
    }

    /** Bind an OCR reader that throws, standing in for a missing binary. */
    private function fakeOcrThrowing(): void
    {
        $this->instance(OcrReader::class, new class implements OcrReader {
            public function text(string $absolutePath): string
            {
                throw new OcrException('Tesseract binary not found.');
            }

            public function isAvailable(): bool
            {
                return false;
            }
        });

        $this->instance(OcrImagePreprocessor::class, new OcrImagePreprocessor());
    }

    /** Bind a roll of legal practitioners returning a fixed verdict. */
    private function fakeRollReturning(?bool $verdict): void
    {
        $this->instance(\App\Services\BarNumber\BarRollLookup::class, new class($verdict) implements \App\Services\BarNumber\BarRollLookup {
            public function __construct(private ?bool $verdict)
            {
            }

            public function lookup(string $barNumber, string $name): ?bool
            {
                return $this->verdict;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'file_number'         => self::FILE_NUMBER,
            'email'               => 'applicant@example.com',
            'applicant_full_name' => 'Iorkua Kator Daniel',
            'applicant_phone'     => '08031234567',
            'applicant_address'   => '12 Ahmadu Bello Way, Kano',
            'customer_type'       => 'individual',
            'identification_type' => 'nin',
            'id_front'            => UploadedFile::fake()->image('front.jpg', 600, 400),
        ], $overrides);
    }

    private function submit(array $overrides = [])
    {
        return $this->post(route('ols.verification.store'), $this->payload($overrides));
    }

    // ---- Outcomes -----------------------------------------------------------

    public function test_a_matching_name_is_verified_and_stored_privately(): void
    {
        $this->fakeOcrReturning('FEDERAL REPUBLIC OF NIGERIA DANIEL IORKUA KATOR');

        $response = $this->submit();

        $response->assertOk()
            ->assertJson([
                'status'  => 'verified',
                'can_pay' => true,
                'message' => config('id_verification.messages.verified'),
            ]);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $this->assertSame('verified', $row->id_verification_status);
        $this->assertNotNull($row->id_verified_at);
        $this->assertSame(100.0, (float) $row->id_name_match_score);

        // Stored on the private disk under a generated name — never the uploader's.
        Storage::disk('ols_private')->assertExists($row->id_front_path);
        $this->assertStringNotContainsString('front.jpg', $row->id_front_path);

        // Raw OCR text is off by default: no second copy of the document's data.
        $this->assertNull($row->id_ocr_text);
    }

    public function test_a_partial_match_is_review_and_is_not_stored_as_verified(): void
    {
        $this->fakeOcrReturning('IORKUA KATOR');

        $this->submit()->assertOk()->assertJson([
            'status'  => 'review',
            'can_pay' => false,
            'message' => config('id_verification.messages.review'),
        ]);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $this->assertSame('review', $row->id_verification_status);
        $this->assertNull($row->id_verified_at);
    }

    public function test_a_different_name_fails(): void
    {
        $this->fakeOcrReturning('MUSA ALIYU IBRAHIM');

        $this->submit()->assertOk()->assertJson([
            'status'  => 'failed',
            'can_pay' => false,
            'message' => config('id_verification.messages.failed'),
        ]);
    }

    public function test_empty_ocr_text_reports_an_unreadable_document(): void
    {
        $this->fakeOcrReturning('   ');

        $this->submit()->assertOk()->assertJson([
            'status'  => 'failed',
            'message' => config('id_verification.messages.unreadable'),
        ]);
    }

    /**
     * An OCR fault must not break the payment page, must not leak detail, and must
     * not be dressed up as a bad photograph — the applicant would re-upload a
     * perfectly good ID forever while the real fault is a server-side one.
     */
    public function test_an_ocr_exception_is_reported_as_a_temporary_outage(): void
    {
        $this->fakeOcrThrowing();

        $response = $this->submit();

        $response->assertOk()->assertJson([
            'status'  => 'pending',
            'can_pay' => false,
            'message' => config('id_verification.messages.unavailable'),
        ]);

        // No technical detail reaches the browser.
        $response->assertDontSee('Tesseract', false);
        $response->assertDontSee('binary', false);

        // No comparison ran, so the applicant must not be recorded as a mismatch.
        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();
        $this->assertSame('pending', $row->id_verification_status);
        $this->assertNull($row->id_verified_at);
    }

    /** An engine outage must not open the checkout either. */
    public function test_payment_cannot_start_after_an_ocr_outage(): void
    {
        $this->fakeOcrThrowing();
        $this->submit()->assertOk();

        $this->postJson(route('ols.payment.verify'), [
            'reference'   => 'test-ref-outage',
            'purpose_id'  => 1,
            'file_number' => self::FILE_NUMBER,
        ])->assertStatus(422);
    }

    /** The response must never carry the transcript of someone's ID. */
    public function test_the_response_never_returns_raw_ocr_text(): void
    {
        $this->fakeOcrReturning('SECRET TRANSCRIPT DANIEL IORKUA KATOR 12345678901');

        $response = $this->submit();

        $response->assertDontSee('SECRET TRANSCRIPT', false);
        $response->assertDontSee('12345678901', false);
        $this->assertArrayNotHasKey('extracted_text', $response->json());
    }

    // ---- Validation ---------------------------------------------------------

    public function test_the_front_image_is_required(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->postJson(route('ols.verification.store'), array_diff_key($this->payload(), ['id_front' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_front');
    }

    /**
     * One image is the whole submission now, for every ID type. The name is on the
     * front of each accepted document, so a two-sided card needs no second upload.
     */
    public function test_a_single_image_is_enough_for_a_two_sided_card(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        // 'nin' was the two-sided case; no back image is supplied.
        $this->post(route('ols.verification.store'), $this->payload())
            ->assertOk()
            ->assertJson(['status' => 'verified']);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();
        $this->assertNotNull($row->id_front_path);
        $this->assertNull($row->id_back_path, 'No back image should be stored any more.');
    }

    /** A passport's data page is the whole document, and always was. */
    public function test_a_passport_data_page_alone_verifies(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->post(route('ols.verification.store'), $this->payload(['identification_type' => 'international_passport']))
            ->assertOk()
            ->assertJson(['status' => 'verified']);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        // A renamed executable: image extension, wrong content.
        $this->postJson(route('ols.verification.store'), $this->payload([
            'id_front' => UploadedFile::fake()->create('payload.png', 40, 'application/x-msdownload'),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_front');
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $oversized = UploadedFile::fake()->image('huge.jpg')->size(
            (int) config('id_verification.uploads.max_kilobytes') + 512
        );

        $this->postJson(route('ols.verification.store'), $this->payload(['id_front' => $oversized]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_front');
    }

    public function test_an_other_id_type_requires_its_label(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->postJson(route('ols.verification.store'), $this->payload(['identification_type' => 'other']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('identification_type_other');
    }

    // ---- Payment gate -------------------------------------------------------

    public function test_payment_cannot_start_when_the_result_is_review(): void
    {
        $this->fakeOcrReturning('IORKUA KATOR');
        $this->submit()->assertOk();

        $this->postJson(route('ols.payment.verify'), [
            'reference'   => 'test-ref-review',
            'purpose_id'  => 1,
            'file_number' => self::FILE_NUMBER,
        ])->assertStatus(422);

        // No payment row may exist for a search that was never verified.
        $this->assertDatabaseMissing(
            'legal_search_online_payments',
            ['reference' => 'test-ref-review'],
            'sqlsrv'
        );
    }

    public function test_payment_cannot_start_when_the_result_is_failed(): void
    {
        $this->fakeOcrReturning('MUSA ALIYU IBRAHIM');
        $this->submit()->assertOk();

        $this->postJson(route('ols.payment.verify'), [
            'reference'   => 'test-ref-failed',
            'purpose_id'  => 1,
            'file_number' => self::FILE_NUMBER,
        ])->assertStatus(422);
    }

    /** Without any verification at all the gate must also hold. */
    public function test_payment_cannot_start_without_a_verification(): void
    {
        $this->postJson(route('ols.payment.verify'), [
            'reference'   => 'test-ref-none',
            'purpose_id'  => 1,
            'file_number' => self::FILE_NUMBER,
        ])->assertStatus(422);
    }

    // ---- Duplicate submission ----------------------------------------------

    public function test_resubmitting_updates_the_same_row_rather_than_duplicating(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->submit()->assertOk();
        $this->submit()->assertOk();

        $this->assertSame(
            1,
            LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->count(),
            'A repeated submission must update the session\'s verification, not create a second applicant record.'
        );
    }

    // ---- Document authorization --------------------------------------------

    public function test_a_guest_cannot_open_a_stored_identification_document(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->submit()->assertOk();

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        // Unauthenticated: the staff guard redirects to login, never to the file.
        $this->get(route('legal-search-online.admin.verifications.document', [
            'id' => $row->id, 'side' => 'front',
        ]))->assertRedirect();
    }

    /** The applicant's own document path must never reach the page or its JS. */
    public function test_storage_paths_are_never_exposed_to_the_browser(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $response = $this->submit();
        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $response->assertDontSee($row->id_front_path, false);
        $this->assertArrayNotHasKey('id_front_path', $response->json());
    }

    /**
     * The happy path all the way through: a verified applicant's payment is
     * accepted and recorded.
     *
     * Paystack is faked - this asserts our gate lets a verified applicant past,
     * not that Paystack works.
     */
    public function test_payment_proceeds_normally_when_the_result_is_verified(): void
    {
        $purpose = OnlineLsSearchPurpose::active()->first();

        if (!$purpose) {
            $this->markTestSkipped('No active Online Legal Search purpose is configured in this database.');
        }

        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->submit()->assertOk()->assertJson(['status' => 'verified']);

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data'   => [
                    'status'   => 'success',
                    'amount'   => 1000000,
                    'customer' => ['email' => 'applicant@example.com'],
                ],
            ], 200),
        ]);

        $reference = 'test-ref-verified-' . uniqid();

        $this->postJson(route('ols.payment.verify'), [
            'reference'   => $reference,
            'email'       => 'applicant@example.com',
            'purpose_id'  => $purpose->id,
            'file_number' => self::FILE_NUMBER,
        ])->assertOk()->assertJson(['success' => true]);

        $payment = LegalSearchOnlinePayment::where('reference', $reference)->first();
        $this->assertNotNull($payment, 'A verified applicant must be able to complete payment.');

        // The verification is linked forward rather than the applicant being
        // recorded a second time alongside the payment.
        $verification = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();
        $this->assertSame((int) $payment->id, (int) $verification->payment_id);
    }

    /**
     * A second call to /payment/verify for a reference already marked paid takes
     * the idempotent "already_paid" branch, which is a genuinely different code
     * path from the fresh-payment branch above (a refreshed page, a retried
     * Paystack callback, or a double-clicked Pay button all land here). Before
     * this was fixed, that branch never linked payment_id — a verification whose
     * ONLY successful verify call went through it stayed unlinked forever, making
     * it invisible to both the admin "View IYC" screen and the applicant's own
     * read-only summary, which both resolve the verification via payment_id.
     */
    public function test_verification_is_linked_to_payment_even_via_the_already_paid_branch(): void
    {
        $purpose = OnlineLsSearchPurpose::active()->first();

        if (!$purpose) {
            $this->markTestSkipped('No active Online Legal Search purpose is configured in this database.');
        }

        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->submit()->assertOk()->assertJson(['status' => 'verified']);

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data'   => [
                    'status'   => 'success',
                    'amount'   => 1000000,
                    'customer' => ['email' => 'applicant@example.com'],
                ],
            ], 200),
        ]);

        $reference = 'test-ref-already-paid-' . uniqid();
        $payload = [
            'reference'   => $reference,
            'email'       => 'applicant@example.com',
            'purpose_id'  => $purpose->id,
            'file_number' => self::FILE_NUMBER,
        ];

        // First call: fresh payment, marks it paid.
        $this->postJson(route('ols.payment.verify'), $payload)->assertOk();

        // Second call, same reference: the idempotent "already_paid" branch.
        $this->postJson(route('ols.payment.verify'), $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'already_paid' => true]);

        $payment = LegalSearchOnlinePayment::where('reference', $reference)->firstOrFail();
        $verification = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $this->assertSame(
            (int) $payment->id,
            (int) $verification->payment_id,
            'The already-paid branch must link the verification to the payment too, not only the fresh-payment branch.'
        );
    }

    /**
     * A signed-in user who is not an approver cannot read a submitted document.
     *
     * This is the "one applicant cannot reach another applicant's ID" guarantee:
     * the ONLY route to the private disk is approver-gated, so an ordinary
     * account - including another applicant with a staff login - is refused.
     */
    public function test_a_non_approver_cannot_open_a_stored_identification_document(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->submit()->assertOk();

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $user = User::query()
            ->where(function ($q) {
                $q->whereNull('assign_role')
                    ->orWhereNotIn('assign_role', ['Director', 'Deputy Director', 'Supper Admin']);
            })
            ->first();

        if (!$user) {
            $this->markTestSkipped('No non-approver user account is available in this database.');
        }

        $this->actingAs($user)
            ->get(route('legal-search-online.admin.verifications.document', [
                'id' => $row->id, 'side' => 'front',
            ]))
            ->assertForbidden();
    }

    /**
     * An approver gets back bytes a browser can actually decode.
     *
     * Asserting only the status code is not enough here: the bug this guards
     * returned a perfectly good 200 with an image content-type whose body did not
     * survive the trip to the browser, so the image rendered as a broken icon. The
     * JPEG magic number at both ends is what proves the body is intact and
     * complete, which is the part that actually regressed.
     */
    public function test_an_approver_receives_intact_image_bytes(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->submit()->assertOk();

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $approver = User::where('assign_role', 'Supper Admin')->first();

        if (!$approver) {
            $this->markTestSkipped('No approver account is available in this database.');
        }

        $response = $this->actingAs($approver)
            ->get(route('legal-search-online.admin.verifications.document', [
                'id' => $row->id, 'side' => 'front',
            ]));

        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();

        $this->assertNotSame('', $body, 'The document response body must not be empty.');
        $this->assertSame(
            "\xFF\xD8",
            substr($body, 0, 2),
            'The body must start with the JPEG start-of-image marker — a truncated or wrapped body will not render.'
        );
        $this->assertSame(
            "\xFF\xD9",
            substr($body, -2),
            'The body must end with the JPEG end-of-image marker, proving nothing was appended or cut off.'
        );
        $this->assertSame(
            strlen($body),
            (int) $response->headers->get('Content-Length'),
            'Content-Length must match the bytes actually sent.'
        );
    }

    // ---- Customer type & Call-to-Bar ---------------------------------------

    /** An individual is unchanged: no bar number, and none recorded. */
    public function test_an_individual_needs_no_bar_number(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->submit()->assertOk()->assertJson(['status' => 'verified']);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $this->assertSame('individual', $row->customer_type);
        $this->assertNull($row->call_to_bar_number);
        $this->assertSame('not_applicable', $row->bar_number_status);
    }

    /** A lawyer must supply one. */
    public function test_a_lawyer_must_supply_a_bar_number(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->postJson(route('ols.verification.store'), $this->payload(['customer_type' => 'lawyer']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('call_to_bar_number');
    }

    /** The customer type itself is constrained to the configured list. */
    public function test_an_unknown_customer_type_is_rejected(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->postJson(route('ols.verification.store'), $this->payload(['customer_type' => 'judge']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_type');
    }

    /**
     * A lawyer still uploads an ID and passes the same name check. The bar number
     * is stored normalised alongside the customer type.
     */
    public function test_a_lawyer_is_verified_on_the_name_and_the_number_is_stored(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');

        $this->submit(['customer_type' => 'lawyer', 'call_to_bar_number' => 'scn-123 456'])
            ->assertOk()
            ->assertJson(['status' => 'verified', 'can_pay' => true]);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();

        $this->assertSame('lawyer', $row->customer_type);
        $this->assertSame('SCN123456', $row->call_to_bar_number);
    }

    /** When the number is printed on the ID, the check finds it. */
    public function test_a_bar_number_found_on_the_id_is_recorded_as_matched(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR SUPREME COURT NUMBER SCN123456');

        $this->submit(['customer_type' => 'lawyer', 'call_to_bar_number' => 'SCN123456'])
            ->assertOk()
            ->assertJson(['status' => 'verified']);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();
        $this->assertSame('matched', $row->bar_number_status);
    }

    /**
     * The case that matters most: a lawyer whose NIN slip does not print the
     * number must still be able to pay. Blocking here would stop every lawyer.
     */
    public function test_an_unconfirmed_bar_number_still_allows_payment(): void
    {
        $this->fakeOcrReturning('FEDERAL REPUBLIC OF NIGERIA DANIEL IORKUA KATOR');

        $this->submit(['customer_type' => 'lawyer', 'call_to_bar_number' => 'SCN123456'])
            ->assertOk()
            ->assertJson(['status' => 'verified', 'can_pay' => true]);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();
        $this->assertSame('unconfirmed', $row->bar_number_status);
        $this->assertNotNull($row->id_verified_at);
    }

    /** A roll that rejects the number pulls a passing name match down to review. */
    public function test_a_rejected_bar_number_downgrades_a_verified_name_to_review(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->fakeRollReturning(false);

        $this->submit(['customer_type' => 'lawyer', 'call_to_bar_number' => 'SCN999999'])
            ->assertOk()
            ->assertJson(['status' => 'review', 'can_pay' => false]);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();
        $this->assertSame('rejected', $row->bar_number_status);
        $this->assertNull($row->id_verified_at);
    }

    /** And a rejected number therefore cannot reach a payment transaction. */
    public function test_payment_cannot_start_after_a_rejected_bar_number(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->fakeRollReturning(false);

        $this->submit(['customer_type' => 'lawyer', 'call_to_bar_number' => 'SCN999999'])->assertOk();

        $this->postJson(route('ols.payment.verify'), [
            'reference'   => 'test-ref-bar-rejected',
            'purpose_id'  => 1,
            'file_number' => self::FILE_NUMBER,
        ])->assertStatus(422);
    }

    /** A roll that confirms an off-document number counts as matched. */
    public function test_a_roll_confirmation_is_recorded_as_matched(): void
    {
        $this->fakeOcrReturning('DANIEL IORKUA KATOR');
        $this->fakeRollReturning(true);

        $this->submit(['customer_type' => 'lawyer', 'call_to_bar_number' => 'SCN123456'])
            ->assertOk()
            ->assertJson(['status' => 'verified']);

        $row = LegalSearchOnlineVerification::where('file_number', self::FILE_NUMBER)->firstOrFail();
        $this->assertSame('matched', $row->bar_number_status);
    }
}
