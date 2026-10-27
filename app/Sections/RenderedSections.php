<?php

namespace App\Sections;

use App\Models\Faq;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * Output of SectionRenderer: the HTML plus facts templates need afterwards.
 */
final class RenderedSections implements Htmlable
{
    /**
     * @param  Collection<int, Faq>  $faqs  FAQs shown on the page (for FAQPage schema)
     */
    public function __construct(
        public readonly string $html,
        public readonly bool $hasH1,
        public readonly Collection $faqs,
    ) {}

    public function toHtml(): string
    {
        return $this->html;
    }
}
