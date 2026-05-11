<?php
/**
 * ┌─────────────────────────────────────────────────────────────┐
 *   CHIPKIE CONTENT LOCALIZER  v5.0
 *   Standalone — no framework, no Composer.
 *
 *   HOW TO ADD A NEW COUNTRY
 *   ─────────────────────────
 *   1. Add a new entry to the COUNTRIES array below.
 *   2. Set 'prefix'    — WordPress subsite path ('/ca', '/nz', etc.)
 *   3. Set 'label'     — display name shown in the UI
 *   4. Set 'expertise' — array of country-specific concepts Claude
 *                        must address; each item becomes one bullet
 *   5. Set 'disclaimer'— locale-appropriate legal disclaimer HTML
 *   6. Upload this file. Done — no other code changes needed.
 * └─────────────────────────────────────────────────────────────┘
 */

// ─── SITE SETTINGS ───────────────────────────────────────────────────────────
// Fill in once. Do not change unless your site URL or credentials change.

define('WP_BASE',       'https://chipkie.com');
define('WP_USER',       '');
define('WP_PASS',       '');
define('CLAUDE_KEY',    '');
define('CLAUDE_MODEL',  'claude-opus-4-6');
define('TOOL_PASSWORD', '');   // Leave empty to disable password protection
define('LOG_FILE',      __DIR__ . '/localizer-debug.log');
define('DONE_FILE',     __DIR__ . '/localizer-done.json');

// The canonical source site — all articles are read from here.
define('SOURCE_PREFIX', '/au');
define('SOURCE_LABEL',  'Australia');

// ─── COUNTRY CONFIGURATION ───────────────────────────────────────────────────
// Each key is the locale code used internally.
// To add Canada: copy any entry, change the key to 'ca', update the fields.

const COUNTRIES = [

    // ── United Kingdom ────────────────────────────────────────────────────────
    'uk' => [
        'prefix' => '/uk',
        'label'  => 'United Kingdom',
        'expertise' => [
            'Joint and several liability: on a joint mortgage the lender can pursue EITHER borrower for 100% of the debt — not just their "share." This is the single most important fact most people get wrong.',
            'Future mortgage capacity: lenders stress-test each borrower against the FULL mortgage debt when assessing future applications — a co-buyer wanting to move or buy a second property in a few years may find themselves unable to qualify.',
            'Declaration of Trust / Deed of Trust: ESSENTIAL to document beneficial interest percentages, what happens on sale, and whether unequal contributions are loans or equity adjustments — without it, courts and HMRC default to equal shares regardless of actual contributions.',
            'TOLATA 1996 (Trusts of Land and Appointment of Trustees Act): either co-owner can apply to court to force a sale even if the other refuses. A major litigation risk most articles overlook entirely.',
            'SDLT surcharge: if EITHER co-buyer already owns property anywhere in the world, the 3% SDLT higher rate applies to the ENTIRE purchase price — even if the other buyer is a first-time buyer. This surprises most co-buyers at completion.',
            'Deed vs contract limitation period: obligations executed as a deed carry a 12-year limitation period vs 6 years for a standard contract — use a deed for maximum enforceability of co-ownership terms.',
            'Tenancy in common vs joint tenancy: TIC is almost always right for non-married co-buyers — unequal shares, independent inheritance rights, no forced survivorship.',
            'Co-ownership agreement essentials: right of first refusal, buy-sell mechanism, exit timeline, shared expense account, occupancy rules, renovation consent thresholds.',
            'HMRC/tax: CGT on sale (principal private residence relief only applies to the portion while it was the owner\'s main home); IHT implications if shares are gifted or held in trust.',
        ],
        'disclaimer' =>
            '<p><em><strong>Disclaimer:</strong> The information provided in this article is for informational '
          . 'purposes only and should not be considered financial or legal advice. Property and lending laws '
          . 'in the United Kingdom vary and may change over time. We always recommend consulting with a qualified '
          . 'solicitor and mortgage broker before entering into a property purchase or financial arrangement '
          . 'with another party.</em></p>',
    ],

    // ── United States ─────────────────────────────────────────────────────────
    'us' => [
        'prefix' => '',
        'label'  => 'United States',
        'expertise' => [
            'Joint and several liability: on a shared mortgage the lender can pursue EITHER borrower for 100% of the debt — not just their "share." This is the single most important fact most people get wrong.',
            'DTI anchor effect: a co-signed mortgage counts 100% against each co-borrower\'s debt-to-income ratio for all future loan applications — even if the co-owner is the one making all the payments. A friend wanting to buy a new home or investment property years later will be anchored by the original co-buy.',
            'IRS Form 1098: the mortgage interest statement is issued under ONE Social Security Number. Co-owners must agree in writing on how to split the deduction — a common source of tax-season conflict. The primary borrower on the 1098 should be the one who can use the deduction most effectively.',
            'Community property states (CA, AZ, TX, NV, WA, ID, LA, NM, WI): if a co-buyer later marries, their spouse may acquire a community property interest in the home. The co-ownership agreement should include a "no-spouse-claim" clause; a prenuptial agreement may also be appropriate.',
            'Tenants in Common vs Joint Tenancy: TIC is almost always right for friends — unequal ownership shares, independent inheritance rights, no forced survivorship.',
            'Co-ownership agreement essentials: right of first refusal, buy-sell/shotgun clause (one names a price, the other must buy or sell at that price — incentivises fairness), exit timeline, shared expense account, occupancy rules, renovation consent thresholds.',
            'State-by-state variation: statute of limitations on written contracts (4–10 years depending on state), deed of trust vs mortgage states, community vs common law property regimes.',
            'Title insurance and escrow: explain their roles plainly — many first-time buyers don\'t understand what they\'re paying for.',
        ],
        'disclaimer' =>
            '<p><em><strong>Disclaimer:</strong> The information provided in this article is for informational '
          . 'purposes only and should not be considered financial or legal advice. Laws and lending criteria '
          . 'vary significantly between states. We always recommend consulting with a qualified real estate '
          . 'attorney and financial advisor before entering into a property purchase or financial arrangement '
          . 'with another party.</em></p>',
    ],

    // ── ADD NEW COUNTRIES BELOW ───────────────────────────────────────────────
    // Example skeleton — uncomment and fill in:
    //
    // 'ca' => [
    //     'prefix' => '/ca',
    //     'label'  => 'Canada',
    //     'expertise' => [
    //         'Example concept specific to Canada...',
    //     ],
    //     'disclaimer' =>
    //         '<p><em><strong>Disclaimer:</strong> ... Canadian disclaimer text ...</em></p>',
    // ],

];

// ─── Runtime ─────────────────────────────────────────────────────────────────
set_time_limit(300);
ignore_user_abort(true);

// ─── Logging ─────────────────────────────────────────────────────────────────

function dlog(string $msg): void {
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND | LOCK_EX);
}

// ─── Utilities ───────────────────────────────────────────────────────────────

function make_slug(string $text): string {
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 200);
}

/**
 * Trim back to the last complete closing block tag if Claude was cut off.
 * Prevents broken HTML reaching WordPress.
 */
function sanitize_body(string $html): string {
    if (preg_match('/<\/(p|ul|ol|h[1-6]|blockquote)>\s*$/s', $html)) {
        return $html;
    }
    if (preg_match('/^(.*<\/(p|ul|ol|h[1-6]|blockquote)>)/s', $html, $m)) {
        dlog('Truncation detected — trimmed to last complete block tag');
        return $m[1];
    }
    return $html;
}

// ─── WordPress API ────────────────────────────────────────────────────────────

function wp_auth(): string {
    return 'Basic ' . base64_encode(WP_USER . ':' . WP_PASS);
}

function wp_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . wp_auth()],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'error' => $err];
}

function wp_update(string $prefix, int $id, array $data): array {
    $url = WP_BASE . $prefix . '/wp-json/wp/v2/posts/' . $id;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . wp_auth(),
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'error' => $err];
}

function fetch_source(int $id): array {
    // Always reads from the canonical source site (SOURCE_PREFIX).
    $url  = WP_BASE . SOURCE_PREFIX . '/wp-json/wp/v2/posts/' . $id . '?context=edit';
    $resp = wp_get($url);

    if ($resp['status'] === 401) {
        $url  = WP_BASE . SOURCE_PREFIX . '/wp-json/wp/v2/posts/' . $id;
        $resp = wp_get($url);
    }

    if ($resp['status'] !== 200) {
        dlog("fetch_source failed: HTTP {$resp['status']} for post $id");
        return ['title' => '', 'content' => ''];
    }

    $post    = json_decode($resp['body'], true);
    $title   = $post['title']['raw']   ?? $post['title']['rendered']   ?? '';
    $content = $post['content']['raw'] ?? $post['content']['rendered'] ?? '';

    $content = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $content);
    $content = strip_tags($content);
    $content = trim(preg_replace('/\s+/', ' ', $content));

    if (strlen($content) > 4000) {
        $content = substr($content, 0, 4000) . '…';
    }

    return ['title' => strip_tags($title), 'content' => $content];
}

// ─── Claude API ───────────────────────────────────────────────────────────────

function claude_call(string $system, string $user, int $max_tokens): string {
    $payload = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => $max_tokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: '          . CLAUDE_KEY,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        dlog("Claude cURL error: $err");
        return '';
    }

    $resp = json_decode($body, true);
    return trim($resp['content'][0]['text'] ?? '');
}

// ─── Content generation ───────────────────────────────────────────────────────

function build_prompt(string $locale): string {
    $country  = COUNTRIES[$locale];
    $label    = $country['label'];

    // Shared base prompt
    $base = 'You are a senior financial and legal content writer specialising in personal finance for ' . $label . '. '
          . 'Your articles are read by real people making consequential financial decisions — write with the authority of a professional adviser and the clarity of a trusted friend. '
          . 'Use natural ' . $label . ' English: native vocabulary, correct spelling conventions, and culturally grounded examples. '
          . 'Do NOT translate or adapt the reference material — write a completely fresh, native article.' . "\n"
          . 'Target length: 900–1050 words. HARD LIMIT: Do not exceed 1050 words — plan your sections to fit. Fewer, well-developed sections beat many shallow ones.' . "\n"
          . 'TONE: Authoritative and direct. "Tough love" where necessary — do not soften hard financial and legal realities. Avoid toxic positivity.' . "\n"
          . 'STRUCTURE: Lead with the most compelling reason to keep reading. Use clear subheadings. End with concrete, actionable advice.' . "\n"
          . 'OUTPUT FORMAT — follow exactly:' . "\n"
          . '- Output only the article body — no meta-commentary, no document title, no disclaimer.' . "\n"
          . '- Use valid HTML only. NEVER use markdown (no ##, no **, no *, no -- dashes).' . "\n"
          . '- Wrap every paragraph in <p> tags.' . "\n"
          . '- Section headings: <h3> only. NEVER use <h1> or <h2> — reserved for the page title.' . "\n"
          . '- Sub-section labels: <strong> inside a <p> tag, not a heading tag.' . "\n"
          . '- Bullet lists: <ul> and <li>. Numbered lists: <ol> and <li>.' . "\n"
          . '- Bold: <strong>. Italic: <em>. No inline CSS, classes, or attributes on any tag.' . "\n"
          . '- Always end with a complete conclusion paragraph. NEVER stop mid-sentence.' . "\n\n";

    // Country-specific expertise — pulled directly from the COUNTRIES config
    $bullets  = implode("\n", array_map(
        fn(string $point) => '- ' . $point,
        $country['expertise']
    ));
    $expertise = 'CRITICAL CONCEPTS — go deep where the topic demands it:' . "\n"
               . $bullets . "\n"
               . '- Do NOT limit yourself to what the reference covers — include important context the reference missed where it genuinely serves the reader';

    return $base . $expertise;
}

function gen_title(string $sourceTitle, string $locale): string {
    $label  = COUNTRIES[$locale]['label'];
    $system = 'You write article titles for a ' . $label . ' personal finance website. '
            . 'Return only the title — no quotes, no trailing punctuation, no commentary.';
    $user   = 'Write a fresh, native ' . $label . ' article title on the same topic as: "' . $sourceTitle . '"';
    return claude_call($system, $user, 100) ?: $sourceTitle;
}

function gen_body(string $newTitle, string $sourceContent, string $locale): string {
    $system = build_prompt($locale);
    $user   = 'Reference material (for topic context only — do NOT copy or rewrite this):'
            . "\n\n" . $sourceContent
            . "\n\n---\n"
            . 'Write a completely fresh, native article titled: "' . $newTitle . '"' . "\n"
            . 'Draw on your full expertise — include important concepts and nuances the reference may have missed. '
            . 'The goal is an article a professional adviser would be proud to have their name on.';
    return sanitize_body(claude_call($system, $user, 2500));
}

function gen_excerpt(string $title, string $body, string $locale): string {
    $label  = COUNTRIES[$locale]['label'];
    $system = 'Write a 1–2 sentence SEO meta description for a ' . $label . ' personal finance article. '
            . 'Return only the excerpt — no commentary.';
    $user   = 'Title: ' . $title . "\n\nArticle:\n" . substr($body, 0, 800);
    return claude_call($system, $user, 150);
}

// ─── Done tracking ────────────────────────────────────────────────────────────

function load_done(): array {
    if (!file_exists(DONE_FILE)) return [];
    return json_decode(file_get_contents(DONE_FILE), true) ?: [];
}

function save_done(array $done): void {
    $fp = fopen(DONE_FILE, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($done, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function mark_done(string $locale, int $id, string $status, string $link = ''): void {
    $done = load_done();
    $done[$locale][$id] = ['status' => $status, 'link' => $link, 'ts' => time()];
    save_done($done);
}

// ─── API actions ──────────────────────────────────────────────────────────────

function do_posts(): array {
    $locale = $_POST['locale'] ?? '';
    if (!isset(COUNTRIES[$locale])) return ['error' => 'Invalid locale'];

    // Always fetch post list from the canonical source site
    $url  = WP_BASE . SOURCE_PREFIX . '/wp-json/wp/v2/posts?per_page=100&status=publish&_fields=id,title,link';
    $resp = wp_get($url);

    if ($resp['status'] !== 200) {
        return ['error' => 'Could not fetch posts from source site: HTTP ' . $resp['status']];
    }

    $posts = json_decode($resp['body'], true) ?: [];
    $done  = load_done();

    $result = [];
    foreach ($posts as $p) {
        $id    = (int)$p['id'];
        $title = strip_tags($p['title']['rendered'] ?? $p['title'] ?? '(no title)');
        $link  = $p['link'] ?? '';
        $st    = $done[$locale][$id]['status'] ?? 'pending';
        $lnk   = $done[$locale][$id]['link']   ?? $link;
        $result[] = ['id' => $id, 'title' => $title, 'link' => $lnk, 'status' => $st];
    }

    return ['posts' => $result];
}

function do_localize(): array {
    $locale = $_POST['locale'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if (!isset(COUNTRIES[$locale])) return ['error' => 'Invalid locale'];
    if ($id <= 0)                   return ['error' => 'Invalid post ID'];

    $destPrefix = COUNTRIES[$locale]['prefix'];

    dlog("Localizing: locale=$locale id=$id dest=$destPrefix");

    $source = fetch_source($id);
    if (empty($source['title']) && empty($source['content'])) {
        return ['error' => 'Could not fetch source post'];
    }

    dlog("Source title: {$source['title']}");

    $newTitle   = gen_title($source['title'], $locale);
    dlog("New title: $newTitle");

    $newBody    = gen_body($newTitle, $source['content'], $locale);
    dlog("Body length: " . strlen($newBody));

    // Append locale disclaimer
    $newBody   .= "\n\n" . COUNTRIES[$locale]['disclaimer'];

    $newExcerpt = gen_excerpt($newTitle, $newBody, $locale);
    $newSlug    = make_slug($newTitle);

    $resp = wp_update($destPrefix, $id, [
        'title'   => $newTitle,
        'content' => $newBody,
        'excerpt' => $newExcerpt,
        'slug'    => $newSlug,
    ]);

    dlog("wp_update: HTTP {$resp['status']}");

    if ($resp['status'] >= 200 && $resp['status'] < 300) {
        $post = json_decode($resp['body'], true);
        $link = $post['link'] ?? '';
        mark_done($locale, $id, 'done', $link);
        return ['ok' => true, 'id' => $id, 'title' => $newTitle, 'link' => $link];
    }

    return [
        'error'  => 'wp_update failed: HTTP ' . $resp['status'],
        'detail' => substr($resp['body'], 0, 300),
    ];
}

function do_sync_done(): array {
    $locale = $_POST['locale'] ?? '';
    if (!isset(COUNTRIES[$locale])) return ['error' => 'Invalid locale'];

    $done    = load_done();
    $entries = $done[$locale] ?? [];
    $result  = [];
    foreach ($entries as $id => $entry) {
        $result[] = ['id' => (int)$id, 'status' => $entry['status'], 'link' => $entry['link'] ?? ''];
    }
    return ['entries' => $result];
}

function do_clear_done(): array {
    $locale = $_POST['locale'] ?? '';
    if ($locale === '__all__') {
        save_done([]);
    } elseif (isset(COUNTRIES[$locale])) {
        $done = load_done();
        unset($done[$locale]);
        save_done($done);
    }
    return ['ok' => true];
}

// ─── Auth + dispatch ──────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (TOOL_PASSWORD !== '' && ($_POST['pw'] ?? '') !== TOOL_PASSWORD) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if      ($action === 'posts')      echo json_encode(do_posts());
    elseif  ($action === 'localize')   echo json_encode(do_localize());
    elseif  ($action === 'sync_done')  echo json_encode(do_sync_done());
    elseif  ($action === 'clear_done') echo json_encode(do_clear_done());
    else                               echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ─── HTML UI ──────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chipkie Localizer</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
body   { font-family: system-ui, -apple-system, sans-serif; background: #f0f2f5; margin: 0; padding: 24px; color: #1a1a2e; }
.wrap  { max-width: 860px; margin: 0 auto; }
.card  { background: #fff; border-radius: 10px; padding: 22px 24px; margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
h1     { margin: 0 0 4px; font-size: 1.35rem; font-weight: 700; }
.sub   { color: #666; font-size: .85rem; margin: 0 0 18px; }
.route { display: inline-flex; align-items: center; gap: 8px; background: #f0f2f5; border-radius: 6px;
         padding: 6px 12px; font-size: .9rem; font-weight: 600; margin-bottom: 16px; }
.arrow { color: #0073aa; font-size: 1.1rem; }
label  { display: block; font-weight: 600; font-size: .85rem; margin-bottom: 5px; color: #444; }
select, input[type=password] { padding: 8px 10px; border: 1px solid #d0d0d0; border-radius: 5px; font-size: .95rem; background: #fff; }
select:focus, input:focus { outline: 2px solid #0073aa; border-color: #0073aa; }
.controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 14px; }
button { padding: 8px 18px; border: none; border-radius: 5px; cursor: pointer; font-size: .9rem; font-weight: 600; transition: background .15s; }
.btn-blue  { background: #0073aa; color: #fff; }
.btn-blue:hover  { background: #005d8f; }
.btn-red   { background: #d63638; color: #fff; }
.btn-red:hover   { background: #b02223; }
.btn-grey  { background: #e8e8e8; color: #333; }
.btn-grey:hover  { background: #d4d4d4; }
button:disabled { opacity: .4; cursor: not-allowed; }
.stats { margin-left: auto; font-size: .88rem; color: #555; font-weight: 600; }
#log   { background: #0f1117; color: #c9d1d9; padding: 14px; border-radius: 6px;
         height: 240px; overflow-y: auto; font-family: 'Menlo', 'Consolas', monospace; font-size: .78rem; line-height: 1.6; }
.log-ok   { color: #3fb950; }
.log-warn { color: #d29922; }
.log-err  { color: #f85149; }
.log-info { color: #8b949e; }
#postList { list-style: none; padding: 0; margin: 0; }
#postList li { padding: 9px 12px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; font-size: .875rem; }
#postList li:last-child { border-bottom: 0; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
.b-done    { background: #d4edda; color: #155724; }
.b-pending { background: #fff3cd; color: #856404; }
.b-error   { background: #f8d7da; color: #721c24; }
.b-running { background: #cce5ff; color: #004085; }
.ptitle { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.plink  { font-size: .78rem; color: #0073aa; text-decoration: none; flex-shrink: 0; }
.plink:hover { text-decoration: underline; }
.section-head { font-size: .8rem; font-weight: 700; color: #888; text-transform: uppercase;
                letter-spacing: .06em; margin: 0 0 10px; }
</style>
</head>
<body>
<div class="wrap">

<!-- Header card -->
<div class="card">
  <h1>Chipkie Content Localizer</h1>
  <p class="sub">v5.0 &nbsp;·&nbsp; Source: <strong><?= htmlspecialchars(SOURCE_LABEL) ?></strong>
    (<?= htmlspecialchars(SOURCE_PREFIX ?: '/') ?>)</p>

  <?php if (TOOL_PASSWORD !== ''): ?>
  <div style="margin-bottom:14px">
    <label for="pw">Tool Password</label>
    <input type="password" id="pw" placeholder="Password" style="max-width:220px">
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:24px;align-items:flex-end;flex-wrap:wrap">
    <div>
      <label for="locale">Target Country</label>
      <select id="locale" onchange="onLocaleChange()">
        <?php foreach (COUNTRIES as $k => $c): ?>
        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($c['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="route">
      <span><?= htmlspecialchars(SOURCE_LABEL) ?></span>
      <span class="arrow">→</span>
      <span id="routeDest"><?= htmlspecialchars(array_values(COUNTRIES)[0]['label']) ?></span>
    </div>
  </div>

  <div class="controls">
    <button class="btn-blue"  id="btnLoad" onclick="loadPosts()">Load Posts</button>
    <button class="btn-blue"  id="btnRun"  onclick="runAll()" disabled>Run All</button>
    <button class="btn-red"   id="btnStop" onclick="stopAll()" disabled>Stop</button>
    <button class="btn-grey"  id="btnClear" onclick="clearDone()">Clear Done</button>
    <span class="stats" id="stats"></span>
  </div>
</div>

<!-- Log card -->
<div class="card" style="padding:16px 18px">
  <p class="section-head">Activity Log</p>
  <div id="log">Ready — select a target country and click Load Posts.</div>
</div>

<!-- Post list card -->
<div class="card" id="cardList" style="display:none">
  <p class="section-head" id="listHead">Posts</p>
  <ul id="postList"></ul>
</div>

</div><!-- /wrap -->

<script>
var posts             = [];
var stopRequested     = false;
var currentController = null;
var syncTimer         = null;

// Country map from PHP for JS use
var countryLabels = <?= json_encode(array_map(fn($c) => $c['label'], COUNTRIES)) ?>;

function pw()        { var el = document.getElementById('pw'); return el ? el.value : ''; }
function getLocale() { return document.getElementById('locale').value; }

function onLocaleChange() {
  var lbl = countryLabels[getLocale()] || getLocale();
  document.getElementById('routeDest').textContent = lbl;
  // Reset list view when locale changes
  posts = [];
  document.getElementById('postList').innerHTML = '';
  document.getElementById('cardList').style.display = 'none';
  document.getElementById('btnRun').disabled = true;
  document.getElementById('stats').textContent = '';
  log('info', 'Locale changed to ' + lbl + '. Click Load Posts.');
}

function log(type, msg) {
  var d   = document.getElementById('log');
  var cls = {ok:'log-ok', warn:'log-warn', err:'log-err', info:'log-info'}[type] || 'log-info';
  var prefix = {ok:'✓', warn:'⏳', err:'✗', info:'·'}[type] || '·';
  d.innerHTML += '<span class="' + cls + '">' + prefix + ' ' + msg + '</span>\n';
  d.scrollTop  = d.scrollHeight;
}

function updateStats() {
  var done  = posts.filter(function(p){ return p.status === 'done'; }).length;
  var err   = posts.filter(function(p){ return p.status === 'error'; }).length;
  var total = posts.length;
  var s = done + ' / ' + total + ' done';
  if (err) s += ' · ' + err + ' error' + (err > 1 ? 's' : '');
  document.getElementById('stats').textContent = s;
}

function setBadge(id, cls, label) {
  var li = document.getElementById('post-' + id);
  if (!li) return;
  var b = li.querySelector('.badge');
  if (b) { b.className = 'badge ' + cls; b.textContent = label; }
}

function updateViewLink(id, url) {
  if (!url) return;
  var li = document.getElementById('post-' + id);
  if (!li) return;
  var a = li.querySelector('.plink');
  if (a) a.href = url;
}

function postRequest(data, signal) {
  data.pw     = pw();
  data.locale = getLocale();
  return fetch(location.href, {
    method:  'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body:    new URLSearchParams(data).toString(),
    signal:  signal || undefined,
  }).then(function(r) { return r.json(); });
}

function loadPosts() {
  document.getElementById('btnRun').disabled = true;
  document.getElementById('postList').innerHTML = '';
  document.getElementById('cardList').style.display = 'none';
  document.getElementById('stats').textContent = '';
  log('info', 'Loading posts from ' + (countryLabels[getLocale()] || getLocale()) + '…');

  postRequest({action: 'posts'}).then(function(d) {
    if (d.error) { log('err', d.error); return; }

    posts = d.posts;
    var ul   = document.getElementById('postList');
    var html = '';
    posts.forEach(function(p) {
      var cls   = p.status === 'done' ? 'b-done' : 'b-pending';
      var badge = p.status === 'done' ? 'done'   : 'pending';
      html += '<li id="post-' + p.id + '">'
            + '<span class="badge ' + cls + '">' + badge + '</span>'
            + '<span class="ptitle">' + p.title + '</span>'
            + '<a class="plink" href="' + (p.link || '#') + '" target="_blank">view ↗</a>'
            + '</li>';
    });
    ul.innerHTML = html;

    var done = posts.filter(function(p){ return p.status === 'done'; }).length;
    document.getElementById('listHead').textContent =
      posts.length + ' posts · ' + done + ' already done';
    document.getElementById('cardList').style.display = '';
    document.getElementById('btnRun').disabled = false;
    updateStats();
    log('info', 'Loaded ' + posts.length + ' posts (' + done + ' already done).');
  }).catch(function(e) { log('err', 'Load failed: ' + e); });
}

function runAll() {
  stopRequested = false;
  document.getElementById('btnStop').disabled = false;
  document.getElementById('btnRun').disabled  = true;
  startAutoSync();

  var queue = posts.filter(function(p) { return p.status !== 'done'; });
  if (!queue.length) {
    log('ok', 'All posts already done.');
    document.getElementById('btnRun').disabled  = false;
    document.getElementById('btnStop').disabled = true;
    stopAutoSync();
    return;
  }
  log('info', 'Starting ' + queue.length + ' articles…');

  function next(i) {
    if (stopRequested) {
      log('warn', 'Stopped after ' + i + ' articles.');
      document.getElementById('btnStop').disabled = true;
      document.getElementById('btnRun').disabled  = false;
      stopAutoSync();
      return;
    }
    if (i >= queue.length) {
      log('ok', 'All articles processed.');
      document.getElementById('btnStop').disabled = true;
      document.getElementById('btnRun').disabled  = false;
      stopAutoSync();
      return;
    }

    var p = queue[i];
    setBadge(p.id, 'b-running', 'running…');
    log('info', '[' + (i + 1) + '/' + queue.length + '] ' + p.title);

    currentController = new AbortController();
    postRequest({action: 'localize', id: p.id}, currentController.signal)
      .then(function(d) {
        currentController = null;
        if (d.ok) {
          p.status = 'done';
          setBadge(p.id, 'b-done', 'done');
          updateViewLink(p.id, d.link);
          log('ok', d.title);
        } else {
          p.status = 'error';
          setBadge(p.id, 'b-error', 'error');
          log('err', p.title + ': ' + (d.error || 'unknown error'));
        }
        updateStats();
        next(i + 1);
      })
      .catch(function(e) {
        currentController = null;
        if (e.name === 'AbortError') {
          log('warn', p.title + ' — aborted');
          document.getElementById('btnStop').disabled = true;
          document.getElementById('btnRun').disabled  = false;
          stopAutoSync();
          return;
        }
        // Gateway timeout — server may still be processing
        p.status = 'pending';
        setBadge(p.id, 'b-pending', 'pending');
        log('warn', p.title + ' — gateway timeout (auto-sync will confirm)');
        updateStats();
        next(i + 1);
      });
  }

  next(0);
}

function stopAll() {
  stopRequested = true;
  if (currentController) { currentController.abort(); currentController = null; }
  document.getElementById('btnStop').disabled = true;
  log('warn', 'Stop requested — no more articles will start.');
}

function startAutoSync() {
  stopAutoSync();
  syncTimer = setInterval(syncDone, 30000);
}

function stopAutoSync() {
  if (syncTimer) { clearInterval(syncTimer); syncTimer = null; }
}

function syncDone() {
  postRequest({action: 'sync_done'}).then(function(d) {
    if (!d.entries) return;
    d.entries.forEach(function(e) {
      var p = posts.find(function(x) { return x.id === e.id; });
      if (p && e.status === 'done' && p.status !== 'done') {
        p.status = 'done';
        setBadge(e.id, 'b-done', 'done');
        updateViewLink(e.id, e.link);
        log('ok', '(sync confirmed) post #' + e.id);
        updateStats();
      }
    });
  }).catch(function(){});
}

function clearDone() {
  var lbl = countryLabels[getLocale()] || getLocale();
  if (!confirm('Clear done markers for ' + lbl + '?\n\nThis does not undo published posts.')) return;
  postRequest({action: 'clear_done'}).then(function(d) {
    if (d.ok) { log('info', 'Done markers cleared for ' + lbl + '.'); loadPosts(); }
  });
}
</script>
</body>
</html>
