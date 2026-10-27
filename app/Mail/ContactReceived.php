<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** New contact form message, to the lead recipients in Settings → Email. */
class ContactReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900];

    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contactMessage->email, $this->contactMessage->name)],
            subject: "New message from {$this->contactMessage->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-received', with: [
            'company' => Settings::get('general.company_name'),
            'adminUrl' => route('admin.messages.show', $this->contactMessage),
        ]);
    }
}
