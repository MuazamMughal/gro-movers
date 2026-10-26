<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['question', 'answer', 'sort', 'is_active'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'faqable');
    }

    public function services(): MorphToMany
    {
        return $this->morphedByMany(Service::class, 'faqable');
    }

    public function areas(): MorphToMany
    {
        return $this->morphedByMany(Area::class, 'faqable');
    }
}
