@php($orange = \App\Support\Settings::get('theme.tertiary', '#F66419'))
@component('mail.layout', ['preheader' => \Illuminate\Support\Str::limit($contactMessage->message, 90)])
    <h1 style="margin:0 0 6px;font-size:22px;">New contact message</h1>
    <p style="margin:0 0 20px;color:#475569;">From {{ $contactMessage->name }} · <a href="mailto:{{ $contactMessage->email }}" style="color:{{ $orange }};">{{ $contactMessage->email }}</a>@if ($contactMessage->phone) · <a href="tel:{{ $contactMessage->phone }}" style="color:{{ $orange }};">{{ \App\Support\Phone::display($contactMessage->phone) }}</a>@endif</p>
    <div style="padding:16px 18px;background:#f1f5fa;border-radius:8px;white-space:pre-wrap;">{{ $contactMessage->message }}</div>
    <p style="margin:28px 0 0;">
        <a href="{{ $adminUrl }}" style="display:inline-block;background:{{ $orange }};color:#ffffff;font-weight:bold;text-decoration:none;padding:12px 22px;border-radius:8px;">Open in admin</a>
    </p>
@endcomponent
