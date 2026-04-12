<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;

class WordPressApiService
{
    private Client $client;
    private string $baseUrl;
    private string $credentials;
    private string $restApiPath;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('wordpress.base_url'), '/');
        $this->restApiPath = config('wordpress.rest_api_path', '/wp-json/wp/v2');
        $this->credentials = base64_encode(
            config('wordpress.username') . ':' . config('wordpress.application_password')
        );

        $this->client = new Client([
            'timeout' => config('wordpress.request_timeout', 30),
            'headers' => [
                'Authorization' => 'Basic ' . $this->credentials,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /**
     * Build the REST API base URL for a given locale subsite.
     */
    private function apiUrl(string $locale, string $endpoint): string
    {
        $locales = config('wordpress.locales');

        if (! isset($locales[$locale])) {
            throw new \InvalidArgumentException("Unknown locale: {$locale}");
        }

        $sitePath = $locales[$locale]['site_path'];

        return $this->baseUrl . $sitePath . $this->restApiPath . '/' . ltrim($endpoint, '/');
    }

    /**
     * Fetch published posts from a subsite, with pagination support.
     *
     * @return Collection<int, array>
     */
    public function getPosts(string $locale, int $perPage = 50, int $page = 1, string $status = 'publish'): Collection
    {
        $url = $this->apiUrl($locale, 'posts');

        try {
            $response = $this->client->get($url, [
                'query' => [
                    'per_page' => $perPage,
                    'page'     => $page,
                    'status'   => $status,
                    '_embed'   => true, // includes featured media, author, terms
                ],
            ]);

            return collect(json_decode($response->getBody()->getContents(), true));
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 400) {
                // page out of range — no more results
                return collect();
            }
            throw $e;
        }
    }

    /**
     * Fetch all posts from a subsite across all pages.
     *
     * @return Collection<int, array>
     */
    public function getAllPosts(string $locale, string $status = 'publish'): Collection
    {
        $perPage = config('wordpress.posts_per_page', 50);
        $all = collect();
        $page = 1;

        do {
            $batch = $this->getPosts($locale, $perPage, $page, $status);
            $all = $all->merge($batch);
            $page++;
        } while ($batch->count() === $perPage);

        return $all;
    }

    /**
     * Fetch a single post by ID.
     */
    public function getPost(string $locale, int $postId): array
    {
        $url = $this->apiUrl($locale, "posts/{$postId}");

        $response = $this->client->get($url, [
            'query' => ['_embed' => true],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Find an existing post on a target subsite that was localized from
     * a specific source post ID. Returns null if none found.
     */
    public function findLocalizedPost(string $targetLocale, int $sourcePostId): ?array
    {
        $metaKey = config('wordpress.source_meta_key', '_chipkie_localized_from');
        $url = $this->apiUrl($targetLocale, 'posts');

        try {
            $response = $this->client->get($url, [
                'query' => [
                    'meta_key'   => $metaKey,
                    'meta_value' => $sourcePostId,
                    'per_page'   => 1,
                ],
            ]);

            $posts = json_decode($response->getBody()->getContents(), true);

            return ! empty($posts) ? $posts[0] : null;
        } catch (RequestException) {
            return null;
        }
    }

    /**
     * Create a new post on a target subsite.
     */
    public function createPost(string $locale, array $data): array
    {
        $url = $this->apiUrl($locale, 'posts');

        $response = $this->client->post($url, [
            'json' => $data,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Update an existing post on a target subsite.
     */
    public function updatePost(string $locale, int $postId, array $data): array
    {
        $url = $this->apiUrl($locale, "posts/{$postId}");

        $response = $this->client->post($url, [
            'json' => $data,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Ensure a category exists on the target subsite (by slug), creating it if needed.
     * Returns the category ID on the target subsite.
     */
    public function ensureCategory(string $locale, array $sourceCategory): int
    {
        $url = $this->apiUrl($locale, 'categories');

        // Check if it already exists by slug
        $response = $this->client->get($url, [
            'query' => ['slug' => $sourceCategory['slug']],
        ]);
        $existing = json_decode($response->getBody()->getContents(), true);

        if (! empty($existing)) {
            return $existing[0]['id'];
        }

        // Create it
        $response = $this->client->post($url, [
            'json' => [
                'name'        => $sourceCategory['name'],
                'slug'        => $sourceCategory['slug'],
                'description' => $sourceCategory['description'] ?? '',
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true)['id'];
    }

    /**
     * Ensure a tag exists on the target subsite (by slug), creating it if needed.
     * Returns the tag ID on the target subsite.
     */
    public function ensureTag(string $locale, array $sourceTag): int
    {
        $url = $this->apiUrl($locale, 'tags');

        $response = $this->client->get($url, [
            'query' => ['slug' => $sourceTag['slug']],
        ]);
        $existing = json_decode($response->getBody()->getContents(), true);

        if (! empty($existing)) {
            return $existing[0]['id'];
        }

        $response = $this->client->post($url, [
            'json' => [
                'name' => $sourceTag['name'],
                'slug' => $sourceTag['slug'],
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true)['id'];
    }

    /**
     * Update post meta on a target subsite post.
     * Requires the WP REST API to have meta fields registered (or use a plugin).
     */
    public function setPostMeta(string $locale, int $postId, string $key, mixed $value): void
    {
        $this->updatePost($locale, $postId, [
            'meta' => [$key => $value],
        ]);
    }
}
