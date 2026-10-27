<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MoveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Options of the quote form's "Type of Move" dropdown, edited inline on one screen.
 */
class MoveTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('move-types/Index', [
            'moveTypes' => MoveType::query()->ordered()->withCount('leads')->get(['id', 'name', 'sort', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', 'unique:move_types,name']]);

        MoveType::query()->create([...$data, 'sort' => (int) MoveType::query()->max('sort') + 1, 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "“{$data['name']}” added to the quote form."]);

        return back();
    }

    public function update(Request $request, MoveType $moveType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', "unique:move_types,name,{$moveType->id}"],
            'is_active' => ['boolean'],
        ]);

        if (! ($data['is_active'] ?? true) && $moveType->is_active && $this->isLastActive($moveType)) {
            return $this->refuse('The quote form needs at least one active move type.');
        }

        $moveType->update($data);

        return back();
    }

    public function destroy(MoveType $moveType): RedirectResponse
    {
        if ($moveType->is_active && $this->isLastActive($moveType)) {
            return $this->refuse('The quote form needs at least one active move type.');
        }

        // Existing leads keep the name: it is stored on each lead when it comes in.
        $moveType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "“{$moveType->name}” removed from the quote form."]);

        return back();
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:move_types,id']])['ids'];

        DB::transaction(fn () => collect($ids)->each(fn (int $id, int $sort) => MoveType::query()->whereKey($id)->update(['sort' => $sort])));

        return back();
    }

    private function isLastActive(MoveType $moveType): bool
    {
        return ! MoveType::query()->active()->whereKeyNot($moveType->id)->exists();
    }

    private function refuse(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
