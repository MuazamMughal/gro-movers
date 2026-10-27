<?php

namespace App\Seo;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** One public URL as Google sees it (see SiteIndex). */
final readonly class SiteUrl
{
    public function __construct(
        public string $path,
        public string $url,
        /** page, service, area, area_service, category, post */
        public string $type,
        public string $label,
        public string $editUrl,
        public string $title,
        public ?string $description,
        public string $h1,
        public string $canonical,
        public bool $index,
        public bool $follow,
        public bool $customTitle,
        public bool $customDescription,
        public ?CarbonInterface $lastmod,
        public Model $model,
    ) {}

    /** Canonical points at this URL (not elsewhere), so it belongs in the sitemap. */
    public function selfCanonical(): bool
    {
        return rtrim($this->canonical, '/') === rtrim($this->url, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'url' => $this->url,
            'type' => $this->type,
            'label' => $this->label,
            'edit_url' => $this->editUrl,
            'title' => $this->title,
            'description' => $this->description,
            'h1' => $this->h1,
            'index' => $this->index,
            'follow' => $this->follow,
            'custom_title' => $this->customTitle,
            'custom_description' => $this->customDescription,
        ];
    }
}
