<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Enums\SystemPage;
use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlugRedirect;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['system_key', 'title', 'slug', 'status', 'template', 'published_at'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasFaqs, HasSections, HasSeo, HasSlugRedirect, SoftDeletes;

    protected function casts(): array
    {
        return [
            'system_key' => SystemPage::class,
            'status' => PageStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public static function system(SystemPage $key): ?self
    {
        return static::query()->where('system_key', $key)->first();
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PageStatus::Published);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    public function publicPath(): ?string
    {
        return $this->pathForSlug($this->slug);
    }

    public function pathForSlug(string $slug): ?string
    {
        if ($this->system_key !== null) {
            if (! $this->system_key->hasPublicUrl()) {
                return null;
            }

            return $this->system_key->fixedPath() ?? '/'.$slug;
        }

        return '/'.$slug;
    }
}
