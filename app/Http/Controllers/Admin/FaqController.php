<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

/**
 * The global FAQ bank. FAQs are written once here and attached to pages,
 * services and areas from their FAQs tab (or picked in an FAQ section).
 */
class FaqController extends Controller
{
    public function index(): Response
    {
        $counts = DB::table('faqables')->selectRaw('faq_id, count(*) as total')->groupBy('faq_id')->pluck('total', 'faq_id');

        return Inertia::render('faqs/Index', [
            'faqs' => Faq::query()->ordered()->get()->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'is_active' => $faq->is_active,
                'attached_count' => (int) ($counts[$faq->id] ?? 0),
            ]),
        ]);
    }

    /** Where one FAQ is shown (loaded when its editor opens). */
    public function show(Faq $faq): array
    {
        return ['usages' => $faq->usages()];
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::query()->create([
            ...$this->validated($request),
            'sort' => (int) Faq::query()->max('sort') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'FAQ added to the bank.']);

        return back();
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'FAQ saved. It updates everywhere it is shown.']);

        return back();
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'FAQ deleted and removed from every page.']);

        return back();
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:faqs,id']])['ids'];

        DB::transaction(fn () => collect($ids)->each(fn (int $id, int $sort) => Faq::query()->whereKey($id)->update(['sort' => $sort])));

        return back();
    }

    /**
     * @return array{question: string, answer: string, is_active: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ]);

        $answer = trim((string) Purifier::clean($data['answer'], 'rich_text'));

        if (trim(strip_tags($answer)) === '') {
            throw ValidationException::withMessages(['answer' => 'Write an answer.']);
        }

        return [
            'question' => trim($data['question']),
            'answer' => $answer,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
