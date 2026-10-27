<?php

namespace App\Blog;

/**
 * A rendered post body. `before` and `after` are split before the 3rd H2,
 * where the mid-article call to action goes (`after` is empty for short posts).
 */
final readonly class RenderedPost
{
    /**
     * @param  list<array{id: string, text: string}>  $toc
     */
    public function __construct(
        public string $before,
        public string $after,
        public array $toc,
    ) {}

    public function hasMidCta(): bool
    {
        return $this->after !== '';
    }
}
