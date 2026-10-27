<?php

namespace App\Mail;

use App\Models\Lead;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** New quote request, to the lead recipients in Settings → Email. */
class LeadReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900];

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->lead->email, $this->lead->full_name)],
            subject: "New quote request: {$this->lead->full_name}, {$this->lead->move_type_name} on {$this->lead->move_date->format('M j, Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.lead-received', with: [
            'company' => Settings::get('general.company_name'),
            'adminUrl' => route('admin.leads.show', $this->lead),
        ]);
    }
}
