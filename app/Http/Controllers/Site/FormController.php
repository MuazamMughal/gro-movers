<?php

namespace App\Http\Controllers\Site;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\ContactRequest;
use App\Http\Requests\Forms\PublicFormRequest;
use App\Http\Requests\Forms\QuoteRequest;
use App\Mail\ContactReceived;
use App\Mail\LeadAutoReply;
use App\Mail\LeadReceived;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\MoveType;
use App\Support\FormGuard;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;

/**
 * POST /forms/quote and /forms/contact (no CSRF: the pages are static HTML).
 * Protected by the same-origin check, a rate limit, the honeypot and the
 * signed start time (see FormGuard). Emails are queued.
 */
class FormController extends Controller
{
    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'msclkid'];

    /** A fresh signed start time for the JS form (static pages carry their build time). */
    public function token(): JsonResponse
    {
        return response()->json(['token' => FormGuard::token()])->header('Cache-Control', 'no-store');
    }

    public function quote(QuoteRequest $request): JsonResponse|RedirectResponse|Response
    {
        if ($blocked = $this->blocked($request, 'quote')) {
            return $blocked;
        }

        $moveType = MoveType::query()->findOrFail($request->integer('move_type_id'));

        $lead = Lead::query()->create([
            ...$request->safe()->only([
                'full_name', 'email',
                'from_address', 'from_city', 'from_state', 'from_zip', 'from_lat', 'from_lng',
                'to_address', 'to_city', 'to_state', 'to_zip', 'to_lat', 'to_lng',
                'move_date',
            ]),
            'phone' => $request->phoneE164(),
            'move_type_id' => $moveType->id,
            'move_type_name' => $moveType->name,
            'status' => LeadStatus::New,
            ...$this->tracking($request),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        Mail::to($this->recipients())->queue(new LeadReceived($lead));

        if (Settings::get('email.auto_reply_enabled')) {
            Mail::to($lead->email, $lead->full_name)->queue(new LeadAutoReply($lead));
        }

        return $this->success($request, 'quote');
    }

    public function contact(ContactRequest $request): JsonResponse|RedirectResponse|Response
    {
        if ($blocked = $this->blocked($request, 'contact')) {
            return $blocked;
        }

        $message = ContactMessage::query()->create([
            ...$request->safe()->only(['name', 'email', 'message']),
            'phone' => $request->phoneE164(),
            'source_url' => $this->tracking($request)['source_url'],
            'ip' => $request->ip(),
        ]);

        Mail::to($this->recipients())->queue(new ContactReceived($message));

        return $this->success($request, 'contact');
    }

    /**
     * Bots that filled the honeypot or submitted too fast get a normal-looking
     * success response and nothing is saved. A missing or forged start time is
     * an error the visitor can fix by reloading.
     */
    private function blocked(PublicFormRequest $request, string $form): JsonResponse|RedirectResponse|Response|null
    {
        $reason = FormGuard::check($request->all());

        if ($reason === null) {
            return null;
        }

        Log::info("Blocked {$form} form submission", ['reason' => $reason, 'ip' => $request->ip()]);

        if ($reason !== 'invalid') {
            return $this->success($request, $form, tracked: false);
        }

        $message = 'Your form expired. Please reload the page and send it again.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['form' => [$message]]], 422);
        }

        // A live page with the form filled in again (and a fresh start time).
        return response()->view('forms.invalid', [
            'form' => $form,
            'errors' => new MessageBag(['form' => $message]),
            'old' => $request->except([FormGuard::HONEYPOT, FormGuard::FIELD]),
            'seo' => Seo::make(title: 'Please send the form again', path: $request->path(), index: false),
        ], 422);
    }

    private function success(PublicFormRequest $request, string $form, bool $tracked = true): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'redirect' => '/thank-you']);
        }

        // Without JS the thank-you page pushes the GA event itself (?sent=quote|contact).
        return redirect()->to($tracked ? "/thank-you?sent={$form}" : '/thank-you');
    }

    /**
     * @return array{source_url: string|null, referrer: string|null, utm: array<string, string>|null, ip: string|null}
     */
    private function tracking(PublicFormRequest $request): array
    {
        $utm = json_decode((string) $request->input('utm'), true);
        $utm = is_array($utm)
            ? array_filter(array_map(fn ($v) => is_scalar($v) ? Str::limit((string) $v, 200, '') : null, Arr::only($utm, static::UTM_KEYS)), 'filled')
            : [];

        return [
            'source_url' => $request->input('source_url') ?: $request->headers->get('referer'),
            'referrer' => $request->input('referrer') ?: null,
            'utm' => $utm ?: null,
            'ip' => $request->ip(),
        ];
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $recipients = array_values(array_filter((array) Settings::get('email.lead_recipients', [])));

        return $recipients ?: [(string) Settings::get('general.email')];
    }
}
