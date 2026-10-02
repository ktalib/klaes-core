<?php

namespace App\Mail;

use App\Models\FileSearchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FileSearchRequestIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FileSearchRequest $request, public string $requesterName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New File Search Request: ' . $this->request->file_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.file_search_request',
            with: [
                'fr'            => $this->request,
                'requesterName' => $this->requesterName,
            ],
        );
    }
}
