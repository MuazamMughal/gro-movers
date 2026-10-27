@include('errors.minimal-site', [
    'code' => 419,
    'title' => 'This page expired',
    'message' => 'The form was open for a long time, so it expired for your security. Go back, reload the page and send it again.',
    'action' => ['label' => 'Go back', 'url' => url()->previous()],
])
