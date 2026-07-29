<?php

namespace App\Mail;

use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use App\Support\RequestUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EquipmentPurchaseApprovalRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EquipmentPurchaseApplication $application,
        public ?User $approver = null,
        public string $urlRoot = '',
    ) {
        if ($this->urlRoot === '') {
            $this->urlRoot = RequestUrl::captureRoot();
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【CE-Group 社員専用】備品購入申請の承認をお願いします',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.equipment-purchase.approval-requested',
            with: [
                'application' => $this->application,
                'approver' => $this->approver,
                'approveUrl' => RequestUrl::withRoot(
                    $this->urlRoot,
                    fn () => route('equipment-purchases.approve', $this->application),
                ),
                'pendingUrl' => RequestUrl::withRoot(
                    $this->urlRoot,
                    fn () => route('equipment-purchases.pending'),
                ),
            ],
        );
    }
}
