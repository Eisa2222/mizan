<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpiryWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Subscription $subscription,
        public int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تنبيه: تبقّى ' . $this->daysRemaining . ' يوم على انتهاء التجربة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.trial-warning',
            with: [
                'tenant'         => $this->tenant,
                'subscription'   => $this->subscription,
                'daysRemaining'  => $this->daysRemaining,
            ],
        );
    }
}
