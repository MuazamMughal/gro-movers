@include('errors.minimal-site', [
    'code' => 503,
    'title' => 'We’ll be right back',
    'message' => 'The website is being updated and will be back in a few minutes.',
    'action' => ['label' => 'Reload', 'url' => url()->current()],
])
