<?php

namespace App\Mail;

use App\Support\RequestUrl;
use App\Support\SocialInsuranceCompanyStats;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SocialInsuranceCompanyThresholdReached extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $company,
        public int $count,
        public int $threshold = SocialInsuranceCompanyStats::THRESHOLD,
        public string $urlRoot = '',
    ) {
        if ($this->urlRoot === '') {
            $this->urlRoot = RequestUrl::captureRoot();
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【CE-Group 社員専用】{$this->company} の社会保険あり人数が{$this->threshold}人に達しました",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.social-insurance.company-threshold-reached',
            with: [
                'company' => $this->company,
                'count' => $this->count,
                'threshold' => $this->threshold,
                'statsUrl' => RequestUrl::withRoot(
                    $this->urlRoot,
                    fn () => route('social-insurance-stats.index'),
                ),
            ],
        );
    }
}
