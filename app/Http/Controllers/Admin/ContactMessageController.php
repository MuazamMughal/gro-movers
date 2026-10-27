<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Phone;
use App\Support\SiteTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Contact form inbox: read/unread and delete. */
class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'unread' => ['nullable', 'boolean'],
        ]);

        $messages = ContactMessage::query()
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('message', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when($filters['unread'] ?? false, fn (Builder $q) => $q->unread())
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ContactMessage $m) => $this->row($m));

        return Inertia::render('messages/Index', [
            'messages' => $messages,
            'filters' => ['q' => $filters['q'] ?? '', 'unread' => (bool) ($filters['unread'] ?? false)],
            'unreadCount' => ContactMessage::query()->unread()->count(),
        ]);
    }

    /** Opening a message marks it read; the inbox shows it in a side panel. */
    public function show(ContactMessage $message): RedirectResponse
    {
        $message->read_at ??= now();
        $message->save();

        return to_route('admin.messages.index', ['open' => $message->id]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $read = $request->validate(['read' => ['required', 'boolean']])['read'];

        $message->update(['read_at' => $read ? ($message->read_at ?? now()) : null]);

        return back();
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Message from {$message->name} deleted."]);

        return to_route('admin.messages.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ContactMessage $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'phone' => $m->phone ? Phone::display($m->phone) : null,
            'phone_href' => $m->phone ? 'tel:'.$m->phone : null,
            'message' => $m->message,
            'source_url' => $m->source_url,
            'is_read' => $m->read_at !== null,
            'created_at' => SiteTime::local($m->created_at)->format('M j, Y g:i A'),
        ];
    }
}
