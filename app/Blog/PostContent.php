<?php

namespace App\Blog;

use App\Models\Media;
use App\View\Components\Picture;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Blog post body: cleaned on save, enhanced on render.
 *
 * On render: H2s get anchor IDs (the table of contents), library images become
 * responsive <picture> elements, tables and YouTube embeds get responsive
 * wrappers, and the body is split before the 3rd H2 for the mid-article CTA.
 */
class PostContent
{
    /** Image sizes hint: the article column is at most 768px wide. */
    public const IMAGE_SIZES = '(min-width: 800px) 768px, 100vw';

    /** Purify editor HTML. Returns null for an empty body. */
    public static function sanitize(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $clean = trim((string) Purifier::clean($html, 'blog'));

        $hasText = trim(html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '';

        return $hasText || str_contains($clean, '<img') || str_contains($clean, '<iframe') ? $clean : null;
    }

    /** IDs of media-library images inside the HTML. */
    public static function mediaIds(?string $html): array
    {
        preg_match_all('/data-media-id="(\d+)"/', (string) $html, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    public static function render(?string $html, bool $splitForCta = true): RenderedPost
    {
        if (blank($html)) {
            return new RenderedPost('', '', []);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="post-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//div[@id="post-root"]')->item(0);

        $toc = static::anchorHeadings($xpath);
        static::wrapTables($dom, $xpath);
        static::wrapEmbeds($dom, $xpath);
        $pictures = static::markImages($dom, $xpath);

        // Top-level children, split before the 3rd H2 when there is one.
        $before = $after = '';
        $h2Seen = 0;
        $inAfter = false;

        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($splitForCta && $node instanceof DOMElement && $node->tagName === 'h2' && ++$h2Seen === 3) {
                $inAfter = true;
            }

            $inAfter ? $after .= $dom->saveHTML($node) : $before .= $dom->saveHTML($node);
        }

        return new RenderedPost(static::fillPictures($before, $pictures), static::fillPictures($after, $pictures), $toc);
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    private static function anchorHeadings(DOMXPath $xpath): array
    {
        $toc = [];
        $used = [];

        foreach ($xpath->query('//h2') as $i => $heading) {
            $text = Str::squish($heading->textContent);

            if ($text === '') {
                continue;
            }

            $base = Str::slug($text) ?: 'section-'.($i + 1);
            $id = $base;
            $n = 2;

            while (isset($used[$id])) {
                $id = $base.'-'.$n++;
            }

            $used[$id] = true;
            $heading->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'text' => $text];
        }

        return $toc;
    }

    private static function wrapTables(DOMDocument $dom, DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//table')) as $table) {
            static::wrap($dom, $table, 'table-wrap');
        }
    }

    private static function wrapEmbeds(DOMDocument $dom, DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//iframe')) as $iframe) {
            $iframe->setAttribute('loading', 'lazy');
            $iframe->setAttribute('allow', 'accelerometer; encrypted-media; gyroscope; picture-in-picture');
            $iframe->setAttribute('allowfullscreen', '');
            $iframe->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            $iframe->getAttribute('title') ?: $iframe->setAttribute('title', 'YouTube video');
            $iframe->removeAttribute('width');
            $iframe->removeAttribute('height');

            // The editor wraps embeds in a <div>; reuse it instead of nesting another.
            $parent = $iframe->parentNode;

            if ($parent instanceof DOMElement && $parent->tagName === 'div' && $parent->getAttribute('id') !== 'post-root' && $parent->childNodes->length === 1) {
                $parent->setAttribute('class', 'video-embed');
            } else {
                static::wrap($dom, $iframe, 'video-embed');
            }
        }
    }

    /**
     * Replace library images with placeholders; return their <picture> HTML by token.
     *
     * @return array<string, string>
     */
    private static function markImages(DOMDocument $dom, DOMXPath $xpath): array
    {
        $images = iterator_to_array($xpath->query('//img'));
        $ids = array_filter(array_map(fn (DOMElement $img) => (int) $img->getAttribute('data-media-id'), $images));
        $media = $ids ? Media::query()->with('variants')->whereIn('id', $ids)->get()->keyBy('id') : collect();
        $pictures = [];

        foreach ($images as $i => $img) {
            $id = (int) $img->getAttribute('data-media-id');

            if (! $id) {
                // Pasted external image: keep it, lazily loaded.
                $img->setAttribute('loading', 'lazy');
                $img->setAttribute('decoding', 'async');

                continue;
            }

            // A lone image in a paragraph would put <figure> inside <p>: replace the paragraph.
            $parent = $img->parentNode;
            $target = $parent instanceof DOMElement && $parent->tagName === 'p' && $parent->childNodes->length === 1 ? $parent : $img;
            $item = $media->get($id);

            if (! $item) {
                // Deleted from the library: drop it rather than show a broken image.
                $target->parentNode->removeChild($target);

                continue;
            }

            $alt = $img->getAttribute('alt') ?: (string) $item->alt;
            $picture = Blade::renderComponent(new Picture($item, sizes: static::IMAGE_SIZES, alt: $alt, imgClass: 'w-full rounded-xl'));

            $html = filled($item->caption)
                ? '<figure>'.$picture.'<figcaption>'.e($item->caption).'</figcaption></figure>'
                : '<figure>'.$picture.'</figure>';

            $token = "post-media-{$i}";
            $pictures[$token] = $html;

            $target->parentNode->replaceChild($dom->createComment($token), $target);
        }

        return $pictures;
    }

    /**
     * @param  array<string, string>  $pictures
     */
    private static function fillPictures(string $html, array $pictures): string
    {
        return preg_replace_callback('/<!--(post-media-\d+)-->/', fn ($m) => $pictures[$m[1]] ?? '', $html);
    }

    private static function wrap(DOMDocument $dom, DOMNode $node, string $class): void
    {
        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('class', $class);
        $node->parentNode->replaceChild($wrapper, $node);
        $wrapper->appendChild($node);
    }
}
