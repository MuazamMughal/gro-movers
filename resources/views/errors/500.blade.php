@include('errors.minimal-site', [
    'code' => 500,
    'title' => 'Something went wrong on our side',
    'message' => 'Sorry about that. Please try again in a minute, or call us and we will help right away.',
    'action' => ['label' => 'Try again', 'url' => url()->current()],
])
