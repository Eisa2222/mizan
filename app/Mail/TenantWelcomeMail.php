<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Subscription $subscription,
        public string $passwordSetupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'مرحباً بك في ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.welcome',
            with: [
                'tenant'           => $this->tenant,
                'subscription'     => $this->subscription,
                'passwordSetupUrl' => $this->passwordSetupUrl,
            ],
        );
    }
}
