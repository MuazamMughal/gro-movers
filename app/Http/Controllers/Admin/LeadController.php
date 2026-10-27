<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\MoveType;
use App\Support\SiteTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quote requests from the website form: list with filters, detail with status
 * and notes, read/unread, CSV export (of the filtered list) and soft delete.
 */
class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $leads = $this->query($filters)
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'id' => $lead->id,
                'full_name' => $lead->full_name,
                'email' => $lead->email,
                'phone' => $lead->phoneDisplay(),
                'route' => collect([$lead->from_city ?: $lead->from_address, $lead->to_city ?: $lead->to_address])->implode(' → '),
                'move_date' => $lead->move_date->format('M j, Y'),
                'move_type' => $lead->move_type_name,
                'status' => $lead->status->value,
                'is_read' => $lead->isRead(),
                'created_at' => SiteTime::local($lead->created_at)->format('M j, g:i A'),
            ]);

        return Inertia::render('leads/Index', [
            'leads' => $leads,
            'filters' => $filters + ['q' => '', 'status' => '', 'move_type' => '', 'from' => '', 'to' => '', 'unread' => false],
            'statuses' => static::statusOptions(),
            'moveTypes' => MoveType::query()->ordered()->get(['id', 'name']),
            'counts' => [
                'unread' => Lead::query()->unread()->count(),
                'today' => Lead::query()->where('created_at', '>=', SiteTime::local(now())->startOfDay()->utc())->count(),
                'all' => Lead::query()->count(),
            ],
        ]);
    }

    public function show(Lead $lead): Response
    {
        if (! $lead->isRead()) {
            $lead->update(['read_at' => now()]);
        }

        $lead->load('notes.user');

        return Inertia::render('leads/Show', [
            'lead' => [
                ...$lead->only([
                    'id', 'full_name', 'email', 'from_address', 'from_city', 'from_state', 'from_zip',
                    'to_address', 'to_city', 'to_state', 'to_zip', 'move_type_name', 'source_url', 'referrer', 'utm', 'ip', 'user_agent',
                ]),
                'phone' => $lead->phoneDisplay(),
                'phone_href' => 'tel:'.$lead->phone,
                'move_date' => $lead->move_date->format('l, F j, Y'),
                'days_until' => (int) SiteTime::local(now())->startOfDay()->diffInDays($lead->move_date->startOfDay(), false),
                'status' => $lead->status->value,
                'from_map' => $lead->mapsUrl('from'),
                'to_map' => $lead->mapsUrl('to'),
                'created_at' => SiteTime::local($lead->created_at)->format('M j, Y \a\t g:i A'),
                'notes' => $lead->notes->map(fn (LeadNote $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->user?->name ?? 'Deleted user',
                    'created_at' => SiteTime::local($note->created_at)->format('M j, Y g:i A'),
                ]),
            ],
            'statuses' => static::statusOptions(),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'read' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['status'])) {
            $lead->status = LeadStatus::from($data['status']);
        }

        if (array_key_exists('read', $data)) {
            $lead->read_at = $data['read'] ? ($lead->read_at ?? now()) : null;
        }

        $lead->save();

        return back();
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Quote request from {$lead->full_name} deleted."]);

        return to_route('admin.leads.index');
    }

    public function storeNote(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $lead->notes()->create(['body' => $data['body'], 'user_id' => $request->user()->id]);

        return back();
    }

    public function destroyNote(Lead $lead, LeadNote $note): RedirectResponse
    {
        abort_unless($note->lead_id === $lead->id, 404);

        $note->delete();

        return back();
    }

    /** CSV of the leads matching the current filters (opens in Excel / Google Sheets). */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($this->filters($request))->latest();
        $name = 'quote-requests-'.SiteTime::local(now())->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly
            fputcsv($out, [
                'ID', 'Received', 'Status', 'Full name', 'Email', 'Phone',
                'Moving from', 'From city', 'From state', 'From ZIP',
                'Moving to', 'To city', 'To state', 'To ZIP',
                'Moving date', 'Type of move', 'Source page', 'Referrer', 'UTM source', 'UTM medium', 'UTM campaign',
            ]);

            $query->chunk(500, function ($leads) use ($out) {
                foreach ($leads as $lead) {
                    fputcsv($out, array_map(static::safeCell(...), [
                        $lead->id,
                        SiteTime::local($lead->created_at)->format('Y-m-d H:i'),
                        $lead->status->value,
                        $lead->full_name,
                        $lead->email,
                        $lead->phoneDisplay(),
                        $lead->from_address, $lead->from_city, $lead->from_state, $lead->from_zip,
                        $lead->to_address, $lead->to_city, $lead->to_state, $lead->to_zip,
                        $lead->move_date->format('Y-m-d'),
                        $lead->move_type_name,
                        $lead->source_url,
                        $lead->referrer,
                        $lead->utm['utm_source'] ?? '',
                        $lead->utm['utm_medium'] ?? '',
                        $lead->utm['utm_campaign'] ?? '',
                    ]));
                }
            });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Cells starting with = + - @ would run as formulas in Excel (CSV injection): prefix them.
     */
    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'move_type' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'unread' => ['nullable', 'boolean'],
        ]), fn ($v) => $v !== null && $v !== '' && $v !== false && $v !== '0');
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function query(array $f): Builder
    {
        $zone = SiteTime::zone();

        return Lead::query()
            ->when($f['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $digits = preg_replace('/\D+/', '', $term);

                $q->where(fn (Builder $q) => $q->where('full_name', 'like', $like)->orWhere('email', 'like', $like)
                    ->when(strlen($digits) >= 3, fn (Builder $q) => $q->orWhere('phone', 'like', '%'.$digits.'%')));
            })
            ->when($f['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($f['move_type'] ?? null, fn (Builder $q, $id) => $q->where('move_type_id', $id))
            // Dates are the company's local days.
            ->when($f['from'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '>=', CarbonImmutable::parse($d, $zone)->startOfDay()->utc()))
            ->when($f['to'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '<=', CarbonImmutable::parse($d, $zone)->endOfDay()->utc()))
            ->when($f['unread'] ?? null, fn (Builder $q) => $q->unread());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return array_map(fn (LeadStatus $s) => ['value' => $s->value, 'label' => ucfirst($s->value)], LeadStatus::cases());
    }
}
