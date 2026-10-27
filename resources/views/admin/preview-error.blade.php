<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Preview not available</title>
</head>
<body style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 4rem auto; padding: 0 1rem; color: #0A2E5C;">
    @if ($expired ?? false)
        <h1>This preview has expired</h1>
        <p>Previews show unsaved changes at the moment you click Preview. Go back to the page editor and click Preview again.</p>
    @else
        <h1>Preview not available yet</h1>
        <p>Fix these fields in the editor, then click Preview again:</p>
        <ul>
            @foreach ($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
