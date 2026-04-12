<?php

namespace App\Services;

use GuzzleHttp\Client;

class ContentLocalizationService
{
    private Client $client;
    private string $model;

    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct()
    {
        $this->model = config('wordpress.anthropic_model', 'claude-opus-4-6');

        $this->client = new Client([
            'timeout' => 120,
            'headers' => [
                'x-api-key'         => config('wordpress.anthropic_api_key'),
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ],
        ]);
    }

    /**
     * Localize a WordPress post (title + content) from the AU source locale
     * to the given target locale (us or uk).
     *
     * Returns an array with 'title' and 'content' keys.
     */
    public function localizePost(array $post, string $fromLocale, string $toLocale): array
    {
        $fromConfig = config("wordpress.locales.{$fromLocale}");
        $toConfig   = config("wordpress.locales.{$toLocale}");

        $systemPrompt = $this->buildSystemPrompt($fromConfig, $toConfig);

        $title   = $post['title']['raw'] ?? $post['title']['rendered'] ?? '';
        $content = $post['content']['raw'] ?? strip_tags($post['content']['rendered'] ?? '');
        $excerpt = $post['excerpt']['raw'] ?? strip_tags($post['excerpt']['rendered'] ?? '');

        $localizedTitle   = $this->rewrite($title, $systemPrompt, 'title', $fromConfig, $toConfig);
        $localizedContent = $this->rewrite($content, $systemPrompt, 'body', $fromConfig, $toConfig);
        $localizedExcerpt = $excerpt
            ? $this->rewrite($excerpt, $systemPrompt, 'excerpt', $fromConfig, $toConfig)
            : '';

        return [
            'title'   => $localizedTitle,
            'content' => $localizedContent,
            'excerpt' => $localizedExcerpt,
        ];
    }

    /**
     * Call Claude to rewrite a piece of text for the target locale.
     */
    private function rewrite(string $text, string $systemPrompt, string $type, array $fromConfig, array $toConfig): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        $userMessage = match ($type) {
            'title'   => "Localize this blog post TITLE:\n\n{$text}",
            'excerpt' => "Localize this blog post EXCERPT:\n\n{$text}",
            default   => "Localize this blog post BODY CONTENT (preserve all HTML tags exactly as-is, only change text):\n\n{$text}",
        };

        $response = $this->client->post(self::API_URL, [
            'json' => [
                'model'      => $this->model,
                'max_tokens' => 8192,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return trim($body['content'][0]['text'] ?? $text);
    }

    /**
     * Build the localization system prompt tailored to the source/target locale pair.
     */
    private function buildSystemPrompt(array $fromConfig, array $toConfig): string
    {
        $fromLabel    = $fromConfig['label'];
        $toLabel      = $toConfig['label'];
        $fromCurrency = $fromConfig['currency'];
        $toCurrency   = $toConfig['currency'];
        $fromSymbol   = $fromConfig['currency_prefix'];
        $toSymbol     = $toConfig['currency_prefix'];
        $fromTax      = $fromConfig['tax_label'];
        $toTax        = $toConfig['tax_label'];
        $fromDate     = $fromConfig['date_format'];
        $toDate       = $toConfig['date_format'];
        $fromPhone    = $fromConfig['phone_country_code'];
        $toPhone      = $toConfig['phone_country_code'];
        $fromSpelling = $fromConfig['spelling'];
        $toSpelling   = $toConfig['spelling'];

        $spellingNote = '';
        if ($fromSpelling !== $toSpelling) {
            if ($toSpelling === 'american') {
                $spellingNote = <<<TEXT
- SPELLING: Convert British/Australian spellings to American English:
  - Words ending in -ise → -ize (e.g. "organise" → "organize", "recognise" → "recognize")
  - Words ending in -our → -or (e.g. "colour" → "color", "honour" → "honor", "behaviour" → "behavior")
  - Words ending in -re → -er (e.g. "centre" → "center", "theatre" → "theater", "fibre" → "fiber")
  - Words ending in -ence → -ense where applicable (e.g. "licence" → "license" as a verb)
  - "cheque" → "check", "mum" → "mom", "programme" → "program", "tyre" → "tire"
  - "grey" → "gray", "storey" → "story" (floor), "kerb" → "curb", "aeroplane" → "airplane"
TEXT;
            } else {
                $spellingNote = "- SPELLING: Use {$toLabel} English spelling conventions.\n";
            }
        }

        $culturalNote = $this->culturalNote($fromConfig, $toConfig);

        $currencyNote = <<<TEXT
- CURRENCY: Replace all references to {$fromCurrency} / {$fromSymbol} with {$toCurrency} / {$toSymbol}.
  - Do NOT convert dollar amounts numerically — just update the currency label/symbol.
  - e.g. "A\$500" → "{$toSymbol}500", "\$1,000 AUD" → "\$1,000 {$toCurrency}"
TEXT;

        $taxNote = "- TAX: Replace \"{$fromTax}\" references with \"{$toTax}\".\n";

        $dateNote = $fromDate !== $toDate
            ? "- DATES: Convert date format from {$fromDate} to {$toDate} where dates appear.\n"
            : '';

        $phoneNote = $fromPhone !== $toPhone
            ? "- PHONE NUMBERS: Replace country code {$fromPhone} with {$toPhone} where phone numbers appear.\n"
            : '';

        return <<<PROMPT
You are a professional content localization editor. Your job is to rewrite blog post content from {$fromLabel} English to {$toLabel} English.

Apply ONLY the following localization changes — do NOT rephrase, restructure, or add/remove information:

{$spellingNote}
{$currencyNote}
{$taxNote}
{$dateNote}
{$phoneNote}
{$culturalNote}

IMPORTANT RULES:
- Preserve all HTML tags, attributes, and structure exactly. Only change the text content inside tags.
- Do NOT translate — the language stays English.
- Do NOT add commentary, explanations, or notes — return ONLY the localized text.
- Do NOT change headings structure, formatting, or layout.
- If a term has no clear localization equivalent, keep the original.
- Keep the same tone, length, and meaning as the original.
PROMPT;
    }

    /**
     * Generate locale-specific cultural localization instructions.
     */
    private function culturalNote(array $fromConfig, array $toConfig): string
    {
        $toLocale = $toConfig['locale'];

        if ($toLocale === 'en_US') {
            return <<<TEXT
- CULTURAL TERMS (AU → US):
  - "fortnight" → "two weeks"
  - "arvo" → "afternoon"
  - "mate" → use neutral terms ("friend", "colleague", or remove)
  - "petrol" → "gas"
  - "mobile phone" → "cell phone"
  - "flat" (apartment) → "apartment"
  - "biscuit" (sweet) → "cookie"
  - "chips" (crisps) → "chips" (same, context dependent)
  - "boot" (car) → "trunk"
  - "bonnet" (car) → "hood"
  - "uni" → "college" or "university"
  - "postcode" → "zip code"
  - "solicitor/barrister" → "attorney/lawyer"
  - References to Australian states/cities: keep as-is but add "(Australia)" qualifier if the context is geographically ambiguous.
TEXT;
        }

        if ($toLocale === 'en_GB') {
            return <<<TEXT
- CULTURAL TERMS (AU → UK):
  - Australian state/city references: keep as-is but add "(Australia)" qualifier if the context is geographically ambiguous.
  - "arvo" → "afternoon"
  - "mate" → can keep or use "friend"
  - "petrol" → keep as "petrol" (same in UK)
  - "mobile phone" → "mobile phone" (same in UK)
  - "postcode" → keep as "postcode" (same in UK)
TEXT;
        }

        return '';
    }
}
