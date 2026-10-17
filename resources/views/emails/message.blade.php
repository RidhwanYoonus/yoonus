@php($rtl = app()->getLocale() === 'ar')
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#f6f1e7;font-family:{{ $rtl ? 'Tahoma,Arial' : 'Georgia,serif' }};color:#2b2118;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
<tr><td align="center">
  <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fffdf8;border:1px solid #e8dcc4;border-radius:12px;">
    <tr><td style="padding:24px 32px;border-bottom:1px solid #e8dcc4;text-align:center;letter-spacing:.12em;color:#9a7b2f;font-size:18px;">
      {{ $rtl ? \App\Support\Settings::get('store_name_ar') : \App\Support\Settings::get('store_name') }}
    </td></tr>
    <tr><td style="padding:28px 32px;font-size:15px;line-height:1.6;text-align:{{ $rtl ? 'right' : 'left' }};">
      <h1 style="font-size:20px;margin:0 0 16px;font-weight:normal;">{{ $subjectLine }}</h1>
      @foreach ($lines as $line)
        <p style="margin:0 0 12px;">{{ $line }}</p>
      @endforeach
      @if ($actionUrl)
        <p style="margin:24px 0;text-align:center;">
          <a href="{{ $actionUrl }}" style="display:inline-block;background:#9a7b2f;color:#fff;text-decoration:none;padding:12px 28px;border-radius:999px;">{{ $actionText }}</a>
        </p>
      @endif
    </td></tr>
    <tr><td style="padding:16px 32px;border-top:1px solid #e8dcc4;font-size:12px;color:#7a6a55;text-align:center;">
      {{ \App\Support\Settings::get('store_address') }} · TRN {{ \App\Support\Settings::get('store_trn') }}
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
