<?php

namespace App\Console\Commands;

use App\Services\ContentLocalizationService;
use App\Services\WordPressApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LocalizeWordPressContent extends Command
{
    protected $signature = 'wp:localize-content
        {--from=au         : Source locale to pull content from (au, us, uk)}
        {--to=*            : Target locale(s) to push localized content to (e.g. --to=us --to=uk)}
        {--post-id=        : Only localize a single post by ID}
        {--limit=          : Maximum number of posts to process (default: all)}
        {--status=publish  : WordPress post status to fetch (publish, draft, etc.)}
        {--skip-existing   : Skip posts that have already been localized to the target}
        {--dry-run         : Preview what would be localized without making any changes}
        {--force           : Re-localize posts even if they already exist on the target}';

    protected $description = 'Auto-localize WordPress Multisite content from one regional subsite to another using Claude AI';

    public function __construct(
        private readonly WordPressApiService $wpApi,
        private readonly ContentLocalizationService $localizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fromLocale = $this->option('from');
        $toLocales  = $this->option('to');
        $postId     = $this->option('post-id');
        $limit      = $this->option('limit') ? (int) $this->option('limit') : null;
        $status     = $this->option('status');
        $dryRun     = $this->option('dry-run');
        $force      = $this->option('force');
        $skipExist  = $this->option('skip-existing');

        $availableLocales = array_keys(config('wordpress.locales', []));

        // --- Validation ---
        if (! in_array($fromLocale, $availableLocales)) {
            $this->error("Unknown --from locale \"{$fromLocale}\". Available: " . implode(', ', $availableLocales));
            return self::FAILURE;
        }

        if (empty($toLocales)) {
            $this->error('Specify at least one --to locale. e.g. --to=us --to=uk');
            return self::FAILURE;
        }

        foreach ($toLocales as $target) {
            if (! in_array($target, $availableLocales)) {
                $this->error("Unknown --to locale \"{$target}\". Available: " . implode(', ', $availableLocales));
                return self::FAILURE;
            }
            if ($target === $fromLocale) {
                $this->error("--to locale \"{$target}\" is the same as --from. Nothing to do.");
                return self::FAILURE;
            }
        }

        if (! config('wordpress.anthropic_api_key')) {
            $this->error('ANTHROPIC_API_KEY is not set. Add it to your .env file.');
            return self::FAILURE;
        }

        // --- Fetch source posts ---
        $fromLabel = config("wordpress.locales.{$fromLocale}.label");
        $this->info("Fetching posts from {$fromLabel} ({$fromLocale})...");

        if ($postId) {
            $posts = collect([$this->wpApi->getPost($fromLocale, (int) $postId)]);
        } else {
            $posts = $this->wpApi->getAllPosts($fromLocale, $status);
        }

        if ($posts->isEmpty()) {
            $this->warn('No posts found on the source subsite.');
            return self::SUCCESS;
        }

        if ($limit !== null) {
            $posts = $posts->take($limit);
        }

        $this->line("Found <comment>{$posts->count()}</comment> post(s) on /{$fromLocale}");

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be made.');
        }

        $metaKey = config('wordpress.source_meta_key', '_chipkie_localized_from');

        // --- Process each target locale ---
        foreach ($toLocales as $toLocale) {
            $toLabel = config("wordpress.locales.{$toLocale}.label");
            $this->newLine();
            $this->info("━━━ Localizing to {$toLabel} ({$toLocale}) ━━━");

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $failed  = 0;

            $bar = $this->output->createProgressBar($posts->count());
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->start();

            foreach ($posts as $post) {
                $sourceId    = $post['id'];
                $sourceTitle = $post['title']['rendered'] ?? "(post #{$sourceId})";

                $bar->setMessage(mb_strimwidth($sourceTitle, 0, 60, '…'));

                try {
                    // Check for an existing localized copy
                    $existingPost = $this->wpApi->findLocalizedPost($toLocale, $sourceId);

                    if ($existingPost && ! $force) {
                        if ($skipExist || ! $force) {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }
                    }

                    if ($dryRun) {
                        $action = $existingPost ? 'UPDATE' : 'CREATE';
                        $this->newLine();
                        $this->line("  [{$action}] [{$fromLocale}→{$toLocale}] #{$sourceId}: {$sourceTitle}");
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // Localize via Claude
                    $localized = $this->localizer->localizePost($post, $fromLocale, $toLocale);

                    // Sync taxonomy (categories/tags) to target subsite
                    $categoryIds = $this->syncCategories($post, $toLocale);
                    $tagIds      = $this->syncTags($post, $toLocale);

                    $postData = [
                        'title'      => $localized['title'],
                        'content'    => $localized['content'],
                        'excerpt'    => $localized['excerpt'],
                        'status'     => $post['status'],
                        'date'       => $post['date'],
                        'categories' => $categoryIds,
                        'tags'       => $tagIds,
                        'meta'       => [$metaKey => $sourceId],
                    ];

                    if ($existingPost) {
                        $this->wpApi->updatePost($toLocale, $existingPost['id'], $postData);
                        $updated++;
                    } else {
                        $this->wpApi->createPost($toLocale, $postData);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("  Failed #{$sourceId} ({$sourceTitle}): {$e->getMessage()}");
                    Log::error('wp:localize-content failed', [
                        'post_id'   => $sourceId,
                        'from'      => $fromLocale,
                        'to'        => $toLocale,
                        'error'     => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->setMessage('Done');
            $bar->finish();
            $this->newLine(2);

            $this->table(
                ['Locale', 'Created', 'Updated', 'Skipped', 'Failed'],
                [[$toLocale, $created, $updated, $skipped, $failed]]
            );
        }

        $this->newLine();
        $this->info('Localization complete.');

        return self::SUCCESS;
    }

    /**
     * Ensure all source post categories exist on the target subsite.
     * Returns an array of category IDs on the target.
     */
    private function syncCategories(array $post, string $toLocale): array
    {
        $embedded    = $post['_embedded']['wp:term'][0] ?? [];
        $categoryIds = [];

        foreach ($embedded as $term) {
            if (($term['taxonomy'] ?? '') === 'category') {
                $categoryIds[] = $this->wpApi->ensureCategory($toLocale, $term);
            }
        }

        return $categoryIds;
    }

    /**
     * Ensure all source post tags exist on the target subsite.
     * Returns an array of tag IDs on the target.
     */
    private function syncTags(array $post, string $toLocale): array
    {
        $embedded = $post['_embedded']['wp:term'][1] ?? [];
        $tagIds   = [];

        foreach ($embedded as $term) {
            if (($term['taxonomy'] ?? '') === 'post_tag') {
                $tagIds[] = $this->wpApi->ensureTag($toLocale, $term);
            }
        }

        return $tagIds;
    }
}
