<?php
/**
 * Chipkie Localizer v4.5
 * Standalone tool — no framework, no Composer.
 * Source always from AU subsite (/au).
 * Generates fresh, locale-native content for UK and US.
 * Appends country-specific disclaimer to every generated article.
 */

define('WP_BASE',       'https://chipkie.com');
define('WP_USER',       '');
define('WP_PASS',       '');
define('CLAUDE_KEY',    '');
define('CLAUDE_MODEL',  'claude-opus-4-6');
define('TOOL_PASSWORD', '');
define('LOG_FILE',      __DIR__ . '/rewriter-debug.log');
define('DONE_FILE',     __DIR__ . '/rewriter-done.json');
define('AU_PREFIX',     '/au');

const SITES = [
    'uk' => ['prefix' => '/uk', 'label' => 'United Kingdom'],
    'us' => ['prefix' => '',    'label' => 'United States'],
];

// ─── Runtime ─────────────────────────────────────────────────────────────────
set_time_limit(300);
ignore_user_abort(true);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function dlog(string $msg): void {
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function make_slug(string $text): string {
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 200);
}

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

function fetch_source(string $prefix, int $id): array {
    // Try raw content first (requires edit context / auth)
    $url  = WP_BASE . $prefix . '/wp-json/wp/v2/posts/' . $id . '?context=edit';
    $resp = wp_get($url);

    if ($resp['status'] === 401) {
        // Fall back to rendered (no edit context needed)
        $url  = WP_BASE . $prefix . '/wp-json/wp/v2/posts/' . $id;
        $resp = wp_get($url);
    }

    if ($resp['status'] !== 200) {
        dlog("fetch_source failed: HTTP {$resp['status']} for {$url}");
        return ['title' => '', 'content' => ''];
    }

    $post    = json_decode($resp['body'], true);
    $title   = $post['title']['raw']   ?? $post['title']['rendered']   ?? '';
    $content = $post['content']['raw'] ?? $post['content']['rendered'] ?? '';

    // Strip script/style blocks, then all HTML tags
    $content = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $content);
    $content = strip_tags($content);
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    // Cap at 4000 chars to keep Claude prompt manageable
    if (strlen($content) > 4000) {
        $content = substr($content, 0, 4000) . '…';
    }

    return ['title' => strip_tags($title), 'content' => $content];
}

// ─── Claude calls ─────────────────────────────────────────────────────────────

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

function build_prompt(string $locale): string {
    $label = SITES[$locale]['label'];

    $base = 'You are an expert financial and legal content writer for ' . $label . '. '
          . 'Write clear, accurate, engaging articles for a general audience. '
          . 'Use natural ' . $label . ' English — vocabulary, spelling, and cultural context native to that country. '
          . 'Do NOT translate or adapt the provided reference — write a completely fresh, native article. '
          . 'Output only the article body — no meta-commentary, no title, no disclaimers. '
          . 'Target word count: 700–1200 words. Use short paragraphs and plain language.'
          . "\n\n";

    if ($locale === 'uk') {
        $expertise = 'EXPERTISE — apply your full knowledge:' . "\n"
                   . '- UK legislation relevant to the topic (Limitation Act 1980, IHT Act 1984, Land Registration Act 2002, Law of Property Act 1925, Trusts of Land and Appointment of Trustees Act 1996, etc.)' . "\n"
                   . '- Real distinctions that matter (e.g. deed vs contract = 12-year vs 6-year limitation period; hard vs soft debt; PET vs chargeable transfer; joint tenancy vs tenancy in common)' . "\n"
                   . '- Practical UK-specific guidance: HMRC rules, Land Registry, conveyancing process, Stamp Duty Land Tax, Help to Buy, shared ownership' . "\n"
                   . '- Do NOT limit yourself to what the reference covers — if you know important context the reference missed, include it';
    } else {
        $expertise = 'EXPERTISE — apply your full knowledge:' . "\n"
                   . '- US federal and state laws relevant to the topic (statutes of limitation vary by state — note this where relevant; community property vs common law states)' . "\n"
                   . '- Real distinctions that matter (e.g. written vs oral contracts, joint tenancy vs tenancy in common, deed of trust vs mortgage)' . "\n"
                   . '- Practical US-specific guidance: IRS rules, title insurance, escrow, co-ownership agreements, estate planning, real estate attorney role' . "\n"
                   . '- Do NOT limit yourself to what the reference covers — if you know important context the reference missed, include it';
    }

    return $base . $expertise;
}

function gen_title(string $sourceTitle, string $locale): string {
    $label  = SITES[$locale]['label'];
    $system = 'You write article titles for a ' . $label . ' personal finance website. '
            . 'Return only the title — no quotes, no punctuation at end, no commentary.';
    $user   = 'Write a fresh, native ' . $label . ' article title on the same topic as: "' . $sourceTitle . '"';
    $result = claude_call($system, $user, 100);
    return $result ?: $sourceTitle;
}

function gen_body(string $newTitle, string $sourceContent, string $locale): string {
    $system = build_prompt($locale);
    $user   = 'Reference material (for topic context only — do NOT copy or rewrite this):'
            . "\n\n" . $sourceContent
            . "\n\n---\nNow write a completely fresh, native article titled: \"" . $newTitle . '"';
    return claude_call($system, $user, 1500);
}

function gen_excerpt(string $title, string $body, string $locale): string {
    $label  = SITES[$locale]['label'];
    $system = 'Write a 1–2 sentence SEO meta description for a ' . $label . ' personal finance article. '
            . 'Return only the excerpt — no commentary.';
    $user   = 'Title: ' . $title . "\n\nArticle:\n" . substr($body, 0, 800);
    return claude_call($system, $user, 150);
}

/**
 * Returns a locale-appropriate HTML disclaimer paragraph.
 * Appended to every generated article body.
 */
function get_disclaimer(string $locale): string {
    if ($locale === 'uk') {
        return '<p><em><strong>Disclaimer:</strong> The information provided in this article is for informational '
             . 'purposes only and should not be considered financial or legal advice. Property and lending laws '
             . 'in the United Kingdom vary and may change over time. We always recommend consulting with a '
             . 'qualified solicitor and mortgage broker before entering into a property purchase or financial '
             . 'arrangement with another party.</em></p>';
    }

    // US
    return '<p><em><strong>Disclaimer:</strong> The information provided in this article is for informational '
         . 'purposes only and should not be considered financial or legal advice. Laws and lending criteria '
         . 'vary significantly between states. We always recommend consulting with a qualified real estate '
         . 'attorney and financial advisor before entering into a property purchase or financial arrangement '
         . 'with another party.</em></p>';
}

// ─── Done tracking ────────────────────────────────────────────────────────────

function load_done(): array {
    if (!file_exists(DONE_FILE)) return [];
    $json = file_get_contents(DONE_FILE);
    return json_decode($json, true) ?: [];
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
    if (!isset(SITES[$locale])) return ['error' => 'Invalid locale'];

    $prefix    = SITES[$locale]['prefix'];
    // For US (empty prefix) fetch list from AU since AU is the canonical source
    $srcPrefix = ($prefix === '') ? AU_PREFIX : $prefix;

    $url  = WP_BASE . $srcPrefix . '/wp-json/wp/v2/posts?per_page=100&status=publish&_fields=id,title,link';
    $resp = wp_get($url);

    if ($resp['status'] !== 200) {
        return ['error' => 'WP fetch failed: HTTP ' . $resp['status']];
    }

    $posts = json_decode($resp['body'], true) ?: [];
    $done  = load_done();

    $result = [];
    foreach ($posts as $p) {
        $id    = (int)$p['id'];
        $title = $p['title']['rendered'] ?? ($p['title'] ?? '(no title)');
        $link  = $p['link'] ?? '';
        $st    = $done[$locale][$id]['status'] ?? 'pending';
        $lnk   = $done[$locale][$id]['link']   ?? $link;
        $result[] = ['id' => $id, 'title' => strip_tags($title), 'link' => $lnk, 'status' => $st];
    }

    return ['posts' => $result];
}

function do_localize(): array {
    $locale = $_POST['locale'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if (!isset(SITES[$locale])) return ['error' => 'Invalid locale'];
    if ($id <= 0)               return ['error' => 'Invalid post ID'];

    $destPrefix = SITES[$locale]['prefix'];

    dlog("Starting localize: locale=$locale id=$id");

    // Always read source from AU — never from a previously-processed copy
    $source = fetch_source(AU_PREFIX, $id);
    if (empty($source['title']) && empty($source['content'])) {
        return ['error' => 'Could not fetch source post from AU'];
    }

    dlog("Source title: {$source['title']}");

    $newTitle   = gen_title($source['title'], $locale);
    dlog("New title: $newTitle");

    $newBody    = gen_body($newTitle, $source['content'], $locale);
    dlog("Body length: " . strlen($newBody));

    // Append locale-specific disclaimer
    $newBody   .= "\n\n" . get_disclaimer($locale);

    $newExcerpt = gen_excerpt($newTitle, $newBody, $locale);

    // Fresh slug derived from new title — avoids matching post-specific 301 redirect rules
    $newSlug    = make_slug($newTitle);

    $resp = wp_update($destPrefix, $id, [
        'title'   => $newTitle,
        'content' => $newBody,
        'excerpt' => $newExcerpt,
        'slug'    => $newSlug,
    ]);

    dlog("wp_update response: HTTP {$resp['status']}");

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

function do_check_done(): array {
    $locale = $_POST['locale'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    $done  = load_done();
    $entry = $done[$locale][$id] ?? null;
    if (!$entry) return ['status' => 'pending'];
    return ['status' => $entry['status'], 'link' => $entry['link'] ?? ''];
}

function do_clear_done(): array {
    save_done([]);
    return ['ok' => true];
}

function do_sync_done(): array {
    $locale = $_POST['locale'] ?? '';
    if (!isset(SITES[$locale])) return ['error' => 'Invalid locale'];

    $done    = load_done();
    $entries = $done[$locale] ?? [];
    $result  = [];
    foreach ($entries as $id => $entry) {
        $result[] = ['id' => (int)$id, 'status' => $entry['status'], 'link' => $entry['link'] ?? ''];
    }
    return ['entries' => $result];
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
    elseif  ($action === 'check_done') echo json_encode(do_check_done());
    elseif  ($action === 'clear_done') echo json_encode(do_clear_done());
    elseif  ($action === 'sync_done')  echo json_encode(do_sync_done());
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
<title>Chipkie Localizer v4.5</title>
<style>
* { box-sizing: border-box; }
body { font-family: system-ui, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #222; }
h1   { margin: 0 0 16px; font-size: 1.4rem; }
.card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
label { display: block; font-weight: 600; margin-bottom: 6px; font-size: .9rem; }
select, input[type=password] { padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
button { padding: 8px 18px; border: none; border-radius: 4px; cursor: pointer; font-size: .9rem; font-weight: 600; }
.btn-primary   { background: #0073aa; color: #fff; }
.btn-primary:hover   { background: #005d8f; }
.btn-danger    { background: #d63638; color: #fff; }
.btn-danger:hover    { background: #b02223; }
.btn-secondary { background: #e5e5e5; color: #333; }
.btn-secondary:hover { background: #d4d4d4; }
button:disabled { opacity: .45; cursor: not-allowed; }
#log { background: #111; color: #eee; padding: 12px; border-radius: 4px; height: 260px; overflow-y: auto; font-family: monospace; font-size: .82rem; line-height: 1.5; }
#postList { list-style: none; padding: 0; margin: 0; }
#postList li { padding: 8px 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px; font-size: .88rem; }
#postList li:last-child { border-bottom: 0; }
.badge { display: inline-block; padding: 2px 9px; border-radius: 12px; font-size: .75rem; font-weight: 700; white-space: nowrap; }
.b-done    { background: #d4edda; color: #155724; }
.b-pending { background: #fff3cd; color: #856404; }
.b-error   { background: #f8d7da; color: #721c24; }
.b-running { background: #cce5ff; color: #004085; }
.plink { font-size: .8rem; color: #0073aa; text-decoration: none; flex-shrink: 0; }
.plink:hover { text-decoration: underline; }
.done { color: #28a745; }
.pend { color: #ffa500; }
.fail { color: #dc3545; }
.info { color: #aaa; }
#controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 14px; }
#progress { font-size: .9rem; color: #555; }
</style>
</head>
<body>

<div class="card">
  <h1>Chipkie Localizer <small style="font-size:.7em;color:#888;font-weight:400">v4.5</small></h1>

  <?php if (TOOL_PASSWORD !== ''): ?>
  <div style="margin-bottom:12px">
    <label for="pw">Tool Password</label>
    <input type="password" id="pw" placeholder="Enter tool password" style="max-width:260px">
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <div>
      <label for="locale">Target Locale</label>
      <select id="locale">
        <?php foreach (SITES as $k => $s): ?>
        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($s['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div id="controls">
    <button class="btn-primary"   id="btnLoad" onclick="loadPosts()">Load Posts</button>
    <button class="btn-primary"   id="btnRun"  onclick="runAll()" disabled>Run All</button>
    <button class="btn-danger"    id="btnStop" onclick="stopAll()" disabled>Stop</button>
    <button class="btn-secondary" onclick="clearDone()">Clear Done</button>
    <span id="progress"></span>
  </div>
</div>

<div class="card">
  <div id="log">Waiting…</div>
</div>

<div class="card" id="cardList" style="display:none">
  <ul id="postList"></ul>
</div>

<script>
var posts             = [];
var stopRequested     = false;
var currentController = null;
var syncTimer         = null;

function pw()       { var el = document.getElementById('pw'); return el ? el.value : ''; }
function getLocale(){ return document.getElementById('locale').value; }

function addLog(html) {
  var d = document.getElementById('log');
  d.innerHTML += html + '<br>';
  d.scrollTop  = d.scrollHeight;
}

function setProgress(done, total) {
  document.getElementById('progress').textContent = done + ' / ' + total + ' done';
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

function post(data, signal) {
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
  addLog('<span class="info">Loading posts from ' + getLocale().toUpperCase() + ' source…</span>');

  post({action: 'posts'}).then(function(d) {
    if (d.error) { addLog('<span class="fail">Error: ' + d.error + '</span>'); return; }
    posts = d.posts;
    var ul = document.getElementById('postList');
    var html = '';
    posts.forEach(function(p) {
      var cls   = p.status === 'done' ? 'b-done' : 'b-pending';
      var label = p.status === 'done' ? 'done'   : 'pending';
      html += '<li id="post-' + p.id + '">'
            + '<span class="badge ' + cls + '">' + label + '</span>'
            + '<span style="flex:1">' + p.title + '</span>'
            + '<a class="plink" href="' + (p.link || '#') + '" target="_blank">view</a>'
            + '</li>';
    });
    ul.innerHTML = html;
    document.getElementById('cardList').style.display = '';
    addLog('<span class="info">Loaded ' + posts.length + ' posts.</span>');
    document.getElementById('btnRun').disabled = false;
    setProgress(posts.filter(function(p){ return p.status === 'done'; }).length, posts.length);
  }).catch(function(e) {
    addLog('<span class="fail">Load error: ' + e + '</span>');
  });
}

function runAll() {
  stopRequested = false;
  document.getElementById('btnStop').disabled = false;
  document.getElementById('btnRun').disabled  = true;
  startAutoSync();

  var queue = posts.filter(function(p) { return p.status !== 'done'; });
  addLog('<span class="info">Starting ' + queue.length + ' articles…</span>');

  function next(i) {
    if (stopRequested) {
      addLog('<span class="pend">Stopped after ' + i + ' articles.</span>');
      document.getElementById('btnStop').disabled = true;
      document.getElementById('btnRun').disabled  = false;
      stopAutoSync();
      return;
    }
    if (i >= queue.length) {
      addLog('<span class="done">All articles processed!</span>');
      document.getElementById('btnStop').disabled = true;
      document.getElementById('btnRun').disabled  = false;
      stopAutoSync();
      return;
    }

    var p = queue[i];
    setBadge(p.id, 'b-running', 'running…');
    addLog('<span class="info">[' + (i + 1) + '/' + queue.length + '] ' + p.title + '</span>');

    currentController = new AbortController();
    post({action: 'localize', id: p.id}, currentController.signal)
      .then(function(d) {
        currentController = null;
        if (d.ok) {
          p.status = 'done';
          setBadge(p.id, 'b-done', 'done');
          updateViewLink(p.id, d.link);
          addLog('<span class="done">✓ ' + d.title + '</span>');
        } else {
          p.status = 'error';
          setBadge(p.id, 'b-error', 'error');
          addLog('<span class="fail">✗ ' + p.title + ': ' + (d.error || 'unknown error') + '</span>');
        }
        setProgress(posts.filter(function(x){ return x.status === 'done'; }).length, posts.length);
        next(i + 1);
      })
      .catch(function(e) {
        currentController = null;
        if (e.name === 'AbortError') {
          // User clicked Stop — don't continue
          addLog('<span class="pend">⏳ ' + p.title + ' — aborted by stop request</span>');
          document.getElementById('btnStop').disabled = true;
          document.getElementById('btnRun').disabled  = false;
          stopAutoSync();
          return;
        }
        // Network/gateway timeout — server may still be processing; mark pending
        p.status = 'pending';
        setBadge(p.id, 'b-pending', 'pending');
        addLog('<span class="pend">⏳ ' + p.title + ' — gateway timeout (auto-sync will confirm)</span>');
        setProgress(posts.filter(function(x){ return x.status === 'done'; }).length, posts.length);
        next(i + 1);
      });
  }

  next(0);
}

function stopAll() {
  stopRequested = true;
  if (currentController) { currentController.abort(); currentController = null; }
  document.getElementById('btnStop').disabled = true;
  addLog('<span class="pend">Stop requested — no more articles will be sent.</span>');
}

function startAutoSync() {
  stopAutoSync();
  syncTimer = setInterval(syncDone, 30000);
}

function stopAutoSync() {
  if (syncTimer) { clearInterval(syncTimer); syncTimer = null; }
}

function syncDone() {
  post({action: 'sync_done'}).then(function(d) {
    if (!d.entries) return;
    d.entries.forEach(function(e) {
      var p = posts.find(function(x) { return x.id === e.id; });
      if (p && e.status === 'done' && p.status !== 'done') {
        p.status = 'done';
        setBadge(e.id, 'b-done', 'done');
        updateViewLink(e.id, e.link);
        addLog('<span class="done">✓ (sync confirmed) post #' + e.id + '</span>');
        setProgress(posts.filter(function(x){ return x.status === 'done'; }).length, posts.length);
      }
    });
  }).catch(function() {});
}

function clearDone() {
  if (!confirm('Clear all done markers? This will not undo published posts.')) return;
  post({action: 'clear_done'}).then(function(d) {
    if (d.ok) {
      addLog('<span class="info">Done list cleared.</span>');
      loadPosts();
    }
  });
}
</script>
</body>
</html>
