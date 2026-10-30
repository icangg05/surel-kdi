<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
</head>
<body style="margin:0;padding:0;background:#ffffff;">
    {{-- Isi sudah disanitasi saat disimpan (App\Support\MailHtml). --}}
    <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2328;max-width:680px;padding:8px 4px;">
        {!! $body !!}
    </div>
</body>
</html>
