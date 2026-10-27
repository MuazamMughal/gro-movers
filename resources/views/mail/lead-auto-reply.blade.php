@component('mail.layout', ['preheader' => 'Thanks for your quote request. We will contact you shortly.'])
    @foreach ($paragraphs as $paragraph)
        <p style="margin:0 0 16px;">{!! nl2br(e($paragraph)) !!}</p>
    @endforeach
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:8px;background:#f1f5fa;border-radius:8px;width:100%;">
        <tr>
            <td style="padding:16px 18px;font-size:14px;line-height:1.7;">
                <strong>Your request</strong><br>
                {{ $lead->move_type_name }} on {{ $lead->move_date->format('l, F j, Y') }}<br>
                From: {{ $lead->from_address }}<br>
                To: {{ $lead->to_address }}
            </td>
        </tr>
    </table>
    <p style="margin:20px 0 0;font-size:14px;color:#475569;">Questions in the meantime? Call us at <a href="{{ \App\Support\Contact::telHref() }}" style="color:#0a2e5c;font-weight:bold;">{{ \App\Support\Contact::phone() }}</a>.</p>
@endcomponent
