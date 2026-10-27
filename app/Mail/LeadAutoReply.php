<?php

namespace App\Mail;

use App\Models\Lead;
use App\Support\Contact;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "We received your request", to the customer. Subject and body come from
 * Settings → Email, with tokens {name} {move_date} {move_type} {company} {phone}.
 */
class LeadAutoReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900];

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->fill((string) Settings::get('email.auto_reply_subject', 'We received your moving quote request')));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.lead-auto-reply', with: [
            'company' => Settings::get('general.company_name'),
            'paragraphs' => preg_split('/\R{2,}/', trim($this->fill((string) Settings::get('email.auto_reply_body', '')))),
        ]);
    }

    /** Replace the template tokens (plain text: the view escapes the result). */
    public function fill(string $template): string
    {
        return strtr($template, [
            '{name}' => $this->lead->full_name,
            '{first_name}' => strtok($this->lead->full_name, ' ') ?: $this->lead->full_name,
            '{move_date}' => $this->lead->move_date->format('l, F j, Y'),
            '{move_type}' => $this->lead->move_type_name,
            '{company}' => (string) Settings::get('general.company_name'),
            '{phone}' => Contact::phone(),
        ]);
    }
}
