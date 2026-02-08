<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ImportReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        private string $linesCount,
        private string $createdPartsCount,
        private string $importDate,
        private string $upatedPartsCSVPath
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Отчет об импорте запасных частей',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.import-report',
            with: [
                'linesCount' => $this->linesCount,
                'createdPartsCount' => $this->createdPartsCount,
                'importDate' => $this->importDate,
            ],

        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->upatedPartsCSVPath)
                ->as('imported_items_' . now()->format('Y-m-d') . '.csv')
                ->withMime('text/csv'),
        ];
    }
}
