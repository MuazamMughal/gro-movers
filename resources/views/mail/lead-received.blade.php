@php
    $orange = \App\Support\Settings::get('theme.tertiary', '#F66419');
    $rows = [
        'Name' => e($lead->full_name),
        'Email' => '<a href="mailto:'.e($lead->email).'" style="color:'.e($orange).';">'.e($lead->email).'</a>',
        'Phone' => '<a href="tel:'.e($lead->phone).'" style="color:'.e($orange).';">'.e($lead->phoneDisplay()).'</a>',
        'Moving from' => '<a href="'.e($lead->mapsUrl('from')).'" style="color:#0a2e5c;">'.e($lead->from_address).'</a>',
        'Moving to' => '<a href="'.e($lead->mapsUrl('to')).'" style="color:#0a2e5c;">'.e($lead->to_address).'</a>',
        'Moving date' => e($lead->move_date->format('l, F j, Y')),
        'Type of move' => e($lead->move_type_name),
        'Sent from' => e($lead->source_url ?? '—'),
    ];
@endphp
@component('mail.layout', ['preheader' => "{$lead->full_name} · {$lead->move_type_name} · {$lead->move_date->format('M j')}"])
    <h1 style="margin:0 0 6px;font-size:22px;">New quote request</h1>
    <p style="margin:0 0 20px;color:#475569;">Received {{ \App\Support\SiteTime::local($lead->created_at)->format('M j, Y \a\t g:i A') }}. Reply to this email to answer {{ $lead->full_name }} directly.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:15px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 12px 10px 0;border-top:1px solid #e2e8f0;color:#64748b;white-space:nowrap;vertical-align:top;width:120px;">{{ $label }}</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;font-weight:bold;word-break:break-word;">{!! $value !!}</td>
            </tr>
        @endforeach
        @if ($lead->utm)
            <tr>
                <td style="padding:10px 12px 10px 0;border-top:1px solid #e2e8f0;color:#64748b;vertical-align:top;">Campaign</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;">{{ collect($lead->utm)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ') }}</td>
            </tr>
        @endif
    </table>
    <p style="margin:28px 0 0;">
        <a href="{{ $adminUrl }}" style="display:inline-block;background:{{ $orange }};color:#ffffff;font-weight:bold;text-decoration:none;padding:12px 22px;border-radius:8px;">Open in admin</a>
    </p>
@endcomponent
