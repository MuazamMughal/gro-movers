{{--
    Branded HTML email shell: table layout and inline styles (email clients ignore <style> and CSS variables).
    Colors follow Settings → Theme. Slots: $slot (body), optional $preheader.
--}}
@php
    $navy = \App\Support\Settings::get('theme.secondary', '#0A2E5C');
    $orange = \App\Support\Settings::get('theme.tertiary', '#F66419');
    $company = \App\Support\Settings::get('general.company_name');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5fa;font-family:Arial,Helvetica,sans-serif;color:{{ $navy }};">
    @isset($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    @endisset
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5fa;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:{{ $navy }};padding:20px 28px;color:#ffffff;font-size:20px;font-weight:bold;">
                            {{ $company }}
                            <div style="height:3px;width:48px;background:{{ $orange }};margin-top:10px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.5;">
                            {{ $company }} · {{ \App\Support\Contact::address() }} · {{ \App\Support\Contact::phone() }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
