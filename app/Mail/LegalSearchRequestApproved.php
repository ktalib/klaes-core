<?php

namespace App\Mail;

use App\Models\LegalSearchOnlineRequest;
use App\Services\LegalSearchApprovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers the approved Legal Search report to the requester as a PDF.
 */
class LegalSearchRequestApproved extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $report Payload from LegalSearchService::buildPrintReport().
     */
    public function __construct(
        public LegalSearchOnlineRequest $searchRequest,
        public array $report,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Legal Search Report — ' . ($this->searchRequest->file_number ?: $this->searchRequest->request_no),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.legal_search_request_approved',
            with: [
                'searchRequest' => $this->searchRequest,
                'invoiceNumber' => app(LegalSearchApprovalService::class)->invoiceNumber($this->searchRequest),
            ],
        );
    }

    /**
     * The report itself, rendered landscape through the PDF-safe template.
     */
    public function attachments(): array
    {
        // Rendered through the service so the attachment and the approver's
        // preview are produced by exactly the same code path.
        $service = app(LegalSearchApprovalService::class);
        $pdf     = $service->renderPdf($this->searchRequest, $this->report);

        $attachments = [
            Attachment::fromData(fn () => $pdf->output(), $service->pdfFileName($this->searchRequest))
                ->withMime('application/pdf'),
        ];

        // The payment invoice rides along with the report. A request with no
        // payment row (staff-created, or a test) simply has no invoice, and an
        // invoice failure must never cost the requester their report.
        if ($this->searchRequest->payment) {
            try {
                $invoice = $service->renderInvoicePdf($this->searchRequest);

                $attachments[] = Attachment::fromData(
                    fn () => $invoice->output(),
                    $service->invoiceFileName($this->searchRequest)
                )->withMime('application/pdf');
            } catch (\Throwable $e) {
                Log::warning('LegalSearchRequestApproved: invoice could not be attached', [
                    'request_id' => $this->searchRequest->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $attachments;
    }
}
