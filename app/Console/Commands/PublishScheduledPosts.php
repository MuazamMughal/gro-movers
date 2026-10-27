<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled posts are visible as soon as their time passes (see Post::published()).
 * This marks them "published" so admin lists, model events and the static
 * rebuild (T14) see the change. Runs every minute from the scheduler / cPanel cron.
 */
#[Signature('blog:publish-scheduled')]
#[Description('Publish scheduled blog posts whose publish time has passed')]
class PublishScheduledPosts extends Command
{
    public function handle(): int
    {
        $due = Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->get();

        // One by one so model events fire for each post.
        $due->each(fn (Post $post) => $post->update(['status' => PostStatus::Published]));

        if ($due->isNotEmpty()) {
            $this->info("Published {$due->count()} scheduled ".str('post')->plural($due->count()).'.');
        }

        return self::SUCCESS;
    }
}
