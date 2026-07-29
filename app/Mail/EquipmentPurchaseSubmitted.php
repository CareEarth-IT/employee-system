<?php

namespace App\Mail;

use App\Models\EquipmentPurchaseApplication;
use App\Support\RequestUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EquipmentPurchaseSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EquipmentPurchaseApplication $application,
        public string $urlRoot = '',
    ) {
        if ($this->urlRoot === '') {
            $this->urlRoot = RequestUrl::captureRoot();
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【CE-Group 社員専用】備品購入申請を受け付けました',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.equipment-purchase.submitted',
            with: [
                'application' => $this->application,
                'detailUrl' => RequestUrl::withRoot(
                    $this->urlRoot,
                    fn () => route('equipment-purchases.show', $this->application),
                ),
            ],
        );
    }
}
