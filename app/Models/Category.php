<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlugRedirect;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'sort'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSeo, HasSlugRedirect, SoftDeletes;

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function publicPath(): string
    {
        return $this->pathForSlug($this->slug);
    }

    public function pathForSlug(string $slug): string
    {
        return '/blog/category/'.$slug;
    }

    protected function seoFallbackTitle(): string
    {
        return $this->name;
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->description;
    }
}
