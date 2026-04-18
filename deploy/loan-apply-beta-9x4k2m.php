<?php
/**
 * Chipkie Loan Chat — Live Beta
 * Upload to the public/ directory on chipkie.com
 * Access at: https://chipkie.com/loan-apply-beta-9x4k2m.php
 *
 * - Chat is powered by Claude (reads ANTHROPIC_API_KEY from .env)
 * - Loan creation calls LoanChatController::store directly via Laravel bootstrap
 * - No changes needed to any other file on the server
 */

// Deployment pack download: visit ?download to get a zip with all three files
if (isset($_GET['download'])) {
    $envContent = <<<'ENV'
# ============================================================
# CHIPKIE AI LOAN CHAT — ENVIRONMENT VARIABLES
# Add these lines to the bottom of my.chipkie.com/.env
# ============================================================

# Anthropic API key — powers the AI loan chat conversation
# Get your key from: https://console.anthropic.com/
ANTHROPIC_API_KEY=sk-ant-YOUR-KEY-HERE
ENV;

    $guideContent = <<<'GUIDE'
====================================================================
  CHIPKIE AI LOAN CHAT — DEPLOYMENT GUIDE
  Hidden URL beta release for my.chipkie.com
====================================================================

ESTIMATED EFFORT: 20-30 minutes

--------------------------------------------------------------------
WHAT THIS DOES
--------------------------------------------------------------------
Adds a hidden AI-powered loan creation page to my.chipkie.com.
Claude (AI) guides the user through a natural conversation to
collect all loan details, then creates a real loan in the existing
database — same as the current flow, just AI-driven.

No existing pages or flows are changed.

--------------------------------------------------------------------
FILES IN THIS PACKAGE
--------------------------------------------------------------------
  loan-apply-beta-9x4k2m.php   → upload to my.chipkie.com/public/
  chipkie-variables.env         → values to add to my.chipkie.com/.env
  CHIPKIE-CHAT-DEPLOY.txt       → this file

--------------------------------------------------------------------
STEP 1 — ADD ENVIRONMENT VARIABLE  (~5 mins)
--------------------------------------------------------------------
Open the .env file in the root of the my.chipkie.com Laravel app
(one level above the public/ folder) and add the contents of
chipkie-variables.env to the bottom.

--------------------------------------------------------------------
STEP 2 — UPLOAD THE PHP FILE  (~5 mins)
--------------------------------------------------------------------
Upload loan-apply-beta-9x4k2m.php into the public/ directory.

  public/
  ├── index.php
  ├── loan-apply-beta-9x4k2m.php   ← here
  └── ...

--------------------------------------------------------------------
STEP 3 — CLEAR CONFIG CACHE  (~2 mins)
--------------------------------------------------------------------
Run via SSH or Plesk terminal:

  php artisan config:clear
  php artisan cache:clear

--------------------------------------------------------------------
STEP 4 — TEST  (~10 mins)
--------------------------------------------------------------------
Visit: https://my.chipkie.com/loan-apply-beta-9x4k2m.php

Run a test loan all the way through and confirm it appears in
the database/dashboard.

--------------------------------------------------------------------
REQUIREMENTS CHECK
--------------------------------------------------------------------
  ✓ PHP 8.1+              (already required by Laravel)
  ✓ PHP curl extension    (check: php -m | grep curl)
  ✓ Laravel app working   (yes — my.chipkie.com)
  ✓ ANTHROPIC_API_KEY     (added in Step 1)

--------------------------------------------------------------------
NO DATABASE CHANGES REQUIRED
--------------------------------------------------------------------
Uses existing loans, users, instalments and loan_tokens tables.
Nothing new to create or migrate.

--------------------------------------------------------------------
MAKING IT THE MAIN FLOW (when ready)
--------------------------------------------------------------------
Full Laravel integration is in the repo (branch: main) at:
  https://github.com/xtrordinate/chipkie

Changes needed for full integration:
  app/Http/Controllers/LoanChatAIController.php  (new)
  routes/web.php                                  (updated)
  config/services.php                             (updated)
  resources/js/Pages/LoanChat.vue                 (updated — needs npm run build)

Estimated effort: 1-2 hours including testing.
====================================================================
GUIDE;

    $tmp = tempnam(sys_get_temp_dir(), 'chipkie_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFile(__FILE__, 'loan-apply-beta-9x4k2m.php');
    $zip->addFromString('chipkie-variables.env', $envContent);
    $zip->addFromString('CHIPKIE-CHAT-DEPLOY.txt', $guideContent);
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="chipkie-deploy-pack.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

// Read ANTHROPIC_API_KEY directly from .env — no full Laravel bootstrap needed for chat
function loadEnvKey(string $key): string
{
    // Already in environment (e.g. Railway / Docker)
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;

    // Parse .env manually
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) return '';
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) return trim($v, " \t\"'");
    }
    return '';
}

// ─── POST ?action=chat — proxy to Anthropic (no Laravel bootstrap) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'chat') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['messages'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    $apiKey = loadEnvKey('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        http_response_code(503);
        echo json_encode(['error' => 'ANTHROPIC_API_KEY is not configured in .env']);
        exit;
    }

    $result = claudeChat($input['messages'], $apiKey);

    if (isset($result['error'])) {
        http_response_code(503);
        echo json_encode($result);
        exit;
    }

    $stopReason = $result['stop_reason'] ?? '';
    $content    = $result['content']    ?? [];

    if ($stopReason === 'tool_use') {
        $toolBlock = $textBlock = null;
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_use') $toolBlock = $block;
            if (($block['type'] ?? '') === 'text')     $textBlock = $block;
        }
        if ($toolBlock && ($toolBlock['name'] ?? '') === 'create_loan') {
            echo json_encode([
                'message'        => $textBlock['text'] ?? 'All set! Create a password below to finalise your loan.',
                'loan_ready'     => true,
                'collected_data' => $toolBlock['input'],
            ]);
            exit;
        }
    }

    $textBlock = null;
    foreach ($content as $block) {
        if (($block['type'] ?? '') === 'text') { $textBlock = $block; break; }
    }
    echo json_encode(['message' => $textBlock['text'] ?? '']);
    exit;
}

// ─── POST ?action=create — bootstrap Laravel only here, create the loan ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'create') {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid JSON body']);
        exit;
    }

    // Bootstrap Laravel (only for this action)
    try {
        require __DIR__ . '/../vendor/autoload.php';
        $app = require_once __DIR__ . '/../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['message' => 'App failed to boot: ' . $e->getMessage()]);
        exit;
    }

    // Build a synthetic request that Laravel's validation understands
    $request = Illuminate\Http\Request::create(
        $_SERVER['REQUEST_URI'], 'POST',
        [], [], [], [],
        json_encode($data)
    );
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Accept', 'application/json');

    $app->instance('request', $request);
    Illuminate\Support\Facades\Facade::clearResolvedInstance('request');

    try {
        $controller = $app->make(App\Http\Controllers\LoanChatController::class);
        $response   = $controller->store($request);
        http_response_code($response->getStatusCode());
        echo $response->getContent();
    } catch (Illuminate\Validation\ValidationException $e) {
        http_response_code(422);
        echo json_encode(['message' => 'Validation failed.', 'errors' => $e->errors()]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['message' => $e->getMessage()]);
    }
    exit;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function claudeChat(array $messages, string $apiKey): array
{
    $payload = json_encode([
        'model'       => 'claude-sonnet-4-6',
        'max_tokens'  => 1024,
        'system'      => buildSystemPrompt(),
        'tools'       => [createLoanTool()],
        'tool_choice' => ['type' => 'auto'],
        'messages'    => $messages,
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $body    = curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr)       return ['error' => 'Network error: ' . $curlErr];
    if ($status !== 200) return ['error' => 'Claude API error (HTTP ' . $status . ')'];

    return json_decode($body, true) ?? ['error' => 'Invalid API response'];
}

function buildSystemPrompt(): string
{
    $today = date('d/m/Y');
    return implode("\n", [
        "You are Chipkie's friendly loan assistant. Chipkie helps people create personal loan agreements between friends and family.",
        "",
        "Today's date is {$today}.",
        "",
        "Your task: guide the user through setting up a loan agreement via natural conversation. Be warm, concise, and use **bold** for key numbers and terms.",
        "",
        "--- LOAN DETAILS ---",
        "* Role: lender (giving money) or borrower (receiving money)?",
        "* Loan type: what is it for? (car, renovation, wedding, holiday, business, personal, etc.)",
        "* Loan name: a friendly name both parties will recognise (e.g. \"Sarah's Car Loan\")",
        "* Amount: must be greater than \$0",
        "* Repayment frequency: Weekly, Fortnightly, or Monthly",
        "* Duration: how long does the loan run? Convert to number of instalments based on frequency",
        "* Interest rate: annual %, or 0 for interest-free. Calculate and show the repayment amount before asking about interest",
        "* Has the money already been exchanged? If yes, on what date? (exchange date must be today or in the past — never future)",
        "* Repayment start date: must be today ({$today}) or a future date. Reject past dates firmly",
        "",
        "--- YOUR DETAILS (the person chatting) ---",
        "* First name and last name",
        "* Email address",
        "* Date of birth (DD/MM/YYYY — must be in the past)",
        "* Country",
        "* Street address, suburb/city, state/province/region, postcode",
        "* Phone number",
        "",
        "--- OTHER PARTY DETAILS ---",
        "* First name and last name",
        "* Email address",
        "* Date of birth (optional)",
        "* State or region",
        "* Phone number",
        "",
        "--- PLAN & ADD-ONS ---",
        "* Plan: Free (free forever, core features) | Premium (\$9.90/mo, tracking & reminders) | Premium Plus (\$5.40/mo, includes legal contract)",
        "* Contract add-on: only ask if they did not choose Premium Plus — legal contract for \$14.95 one-off?",
        "* Extra signers: any additional signatories beyond the two main parties?",
        "* Terms: confirm they agree to Chipkie's terms and conditions",
        "",
        "--- RULES ---",
        "* Collect naturally — group related questions, don't follow the list rigidly",
        "* Use the user's first name once you know it",
        "* If a country looks misspelled, gently check: \"Did you mean [country]?\"",
        "* Show dates as DD/MM/YYYY; pass them to create_loan as YYYY-MM-DD",
        "* Never invent or assume values",
        "* Present a clear bullet-point summary of ALL details and get explicit confirmation before calling create_loan",
        "* Only call create_loan after the user says yes to the summary",
    ]);
}

function createLoanTool(): array
{
    return [
        'name'        => 'create_loan',
        'description' => 'Create the loan agreement. Call ONLY after presenting a full summary and receiving explicit user confirmation.',
        'input_schema' => [
            'type'     => 'object',
            'required' => [
                'role','loan_type','loan_name','amount','frequency','instalments',
                'interest_rate','money_received','start_date',
                'your_first_name','your_last_name','your_email','your_dob',
                'your_country','your_street_address','your_suburb','your_state',
                'your_postcode','your_phone',
                'other_first_name','other_last_name','other_email',
                'other_state','other_phone',
                'extra_signers','plan','contract_add_on',
            ],
            'properties' => [
                'role'                => ['type' => 'string', 'enum' => ['Lender','Borrower']],
                'loan_type'           => ['type' => 'string'],
                'loan_name'           => ['type' => 'string'],
                'amount'              => ['type' => 'number'],
                'frequency'           => ['type' => 'string', 'enum' => ['Weekly','Fortnightly','Monthly']],
                'instalments'         => ['type' => 'integer', 'minimum' => 1],
                'interest_rate'       => ['type' => 'number', 'minimum' => 0],
                'money_received'      => ['type' => 'boolean'],
                'exchange_date'       => ['type' => 'string', 'description' => 'YYYY-MM-DD or empty string'],
                'start_date'          => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'your_first_name'     => ['type' => 'string'],
                'your_last_name'      => ['type' => 'string'],
                'your_email'          => ['type' => 'string'],
                'your_dob'            => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'your_country'        => ['type' => 'string'],
                'your_street_address' => ['type' => 'string'],
                'your_address_2'      => ['type' => 'string', 'description' => 'Optional'],
                'your_suburb'         => ['type' => 'string'],
                'your_state'          => ['type' => 'string'],
                'your_postcode'       => ['type' => 'string'],
                'your_phone'          => ['type' => 'string'],
                'other_first_name'    => ['type' => 'string'],
                'other_last_name'     => ['type' => 'string'],
                'other_email'         => ['type' => 'string'],
                'other_dob'           => ['type' => 'string', 'description' => 'YYYY-MM-DD or empty string'],
                'other_state'         => ['type' => 'string'],
                'other_phone'         => ['type' => 'string'],
                'extra_signers'       => ['type' => 'boolean'],
                'plan'                => ['type' => 'string', 'enum' => ['Free','Premium','Premium Plus']],
                'contract_add_on'     => ['type' => 'boolean'],
            ],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chipkie — New Loan</title>
<script src="https://cdn.jsdelivr.net/npm/vue@3.4.21/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.8/dist/axios.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:ui-sans-serif,system-ui,sans-serif;background:#f9fafb}
.app{display:flex;flex-direction:column;height:100vh;max-width:32rem;margin:0 auto}
.header{background:#004053;color:#fff;padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.av{width:2.5rem;height:2.5rem;border-radius:50%;background:#007c89;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;flex-shrink:0}
.av-sm{width:1.75rem;height:1.75rem;border-radius:50%;background:#007c89;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem;font-weight:700;flex-shrink:0}
.header h1{font-size:.95rem;font-weight:700;line-height:1}
.header p{font-size:.7rem;color:#6dc4bc;margin-top:2px}
.messages{flex:1;overflow-y:auto;padding:1.25rem 1rem;display:flex;flex-direction:column;gap:.75rem}
.row-a{display:flex;align-items:flex-end;gap:.5rem}
.row-u{display:flex;justify-content:flex-end}
.bub-a{background:#fff;color:#004053;border-radius:1rem 1rem 1rem .25rem;padding:.6rem 1rem;max-width:80%;box-shadow:0 1px 3px rgba(0,0,0,.08);font-size:.875rem;line-height:1.6}
.bub-u{background:#007c89;color:#fff;border-radius:1rem 1rem .25rem 1rem;padding:.6rem 1rem;max-width:75%;box-shadow:0 1px 3px rgba(0,0,0,.08);font-size:.875rem;line-height:1.6}
.typing{display:flex;align-items:flex-end;gap:.5rem}
.dots{background:#fff;border-radius:1rem 1rem 1rem .25rem;padding:.75rem 1rem;box-shadow:0 1px 3px rgba(0,0,0,.08);display:flex;gap:4px;align-items:center}
.dot{width:8px;height:8px;background:#d1d5db;border-radius:50%}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
.bottom{background:#fff;border-top:1px solid #e5e7eb;padding:.75rem 1rem;flex-shrink:0}
.input-row{display:flex;gap:.5rem}
.chat-input{flex:1;border:1px solid #e5e7eb;border-radius:999px;padding:.6rem 1rem;font-size:.875rem;outline:none;font-family:inherit}
.chat-input:focus{border-color:#007c89;box-shadow:0 0 0 2px rgba(0,124,137,.2)}
.chat-input:disabled{opacity:.5;background:#f9fafb}
.send-btn{width:2.5rem;height:2.5rem;border-radius:50%;background:#007c89;color:#fff;border:none;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.send-btn:hover{background:#004053}
.send-btn:disabled{opacity:.5;cursor:not-allowed}
.pw-form{display:flex;flex-direction:column;gap:.5rem}
.pw-label{font-size:.75rem;color:#9ca3af;padding:0 .25rem}
.pw-input{border:1px solid #e5e7eb;border-radius:999px;padding:.6rem 1rem;font-size:.875rem;outline:none;font-family:inherit;width:100%}
.pw-input:focus{border-color:#007c89;box-shadow:0 0 0 2px rgba(0,124,137,.2)}
.create-btn{width:100%;padding:.65rem;background:#007c89;color:#fff;border:none;border-radius:999px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:inherit}
.create-btn:hover{background:#004053}
.create-btn:disabled{opacity:.6;cursor:not-allowed}
.error{color:#ef4444;font-size:.75rem;margin-bottom:.5rem;padding:0 .25rem}
.done{text-align:center;padding:.5rem}
.done a{color:#007c89;font-size:.875rem;font-weight:500;text-decoration:none}
.done a:hover{text-decoration:underline}
</style>
</head>
<body>
<div id="app">
    <div class="app">

        <div class="header">
            <div class="av">C</div>
            <div>
                <h1>Chipkie</h1>
                <p>Loan Assistant</p>
            </div>
        </div>

        <div ref="messagesEl" class="messages">
            <template v-for="(msg, i) in messages" :key="i">
                <div v-if="msg.role === 'assistant'" class="row-a">
                    <div class="av-sm">C</div>
                    <div class="bub-a" v-html="md(msg.content)"></div>
                </div>
                <div v-else class="row-u">
                    <div class="bub-u">{{ msg.content }}</div>
                </div>
            </template>

            <div v-if="isTyping" class="typing">
                <div class="av-sm">C</div>
                <div class="dots">
                    <div class="dot" style="animation:bounce 1.2s infinite 0ms"></div>
                    <div class="dot" style="animation:bounce 1.2s infinite 200ms"></div>
                    <div class="dot" style="animation:bounce 1.2s infinite 400ms"></div>
                </div>
            </div>
        </div>

        <div class="bottom">
            <p v-if="error" class="error">{{ error }}</p>

            <!-- Chat input -->
            <div v-if="!loanReady && !submitted" class="input-row">
                <input
                    ref="inputEl"
                    v-model="text"
                    type="text"
                    placeholder="Type your answer…"
                    :disabled="isTyping"
                    autocomplete="off"
                    class="chat-input"
                    v-on:keydown.enter.prevent="send"
                />
                <button type="button" :disabled="isTyping || !text.trim()" class="send-btn" v-on:click="send">&#10148;</button>
            </div>

            <!-- Password + create -->
            <form v-else-if="loanReady && !submitted" class="pw-form" v-on:submit.prevent="createLoan">
                <p class="pw-label">Set your Chipkie password to create the loan</p>
                <input ref="pwEl"  type="password" placeholder="Password (min 8 characters)" autocomplete="new-password" class="pw-input" />
                <input ref="pw2El" type="password" placeholder="Confirm password"            autocomplete="new-password" class="pw-input" />
                <button type="submit" :disabled="submitting" class="create-btn">
                    {{ submitting ? 'Creating loan…' : 'Create Loan' }}
                </button>
            </form>

            <!-- Done -->
            <div v-else class="done">
                <a href="/">Go to dashboard &rarr;</a>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    var OPENING = "Welcome to **Chipkie** \u2014 I'll help you set up your personal loan agreement.\n\nLet's start: are you the **lender** (you're giving the money) or the **borrower** (you're receiving it)?";
    var BASE    = window.location.pathname;

    Vue.createApp({
        setup: function () {
            var ref       = Vue.ref;
            var onMounted = Vue.onMounted;

            var messages   = ref([{ role: 'assistant', content: OPENING }]);
            var text       = ref('');
            var isTyping   = ref(false);
            var error      = ref('');
            var loanReady  = ref(false);
            var collected  = ref(null);
            var submitted  = ref(false);
            var submitting = ref(false);

            var messagesEl = ref(null);
            var inputEl    = ref(null);
            var pwEl       = ref(null);
            var pw2El      = ref(null);

            function md(s) {
                if (!s) return '';
                return s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\*(.*?)\*/g, '<em>$1</em>')
                        .replace(/\n/g, '<br>');
            }

            function scroll() {
                return new Promise(function (r) {
                    setTimeout(function () {
                        if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
                        r();
                    }, 0);
                });
            }

            async function send() {
                var t = text.value.trim();
                if (!t || isTyping.value) return;
                error.value = '';
                text.value  = '';
                messages.value.push({ role: 'user', content: t });
                await scroll();

                isTyping.value = true;
                try {
                    var res  = await axios.post(BASE + '?action=chat', { messages: messages.value });
                    var data = res.data;
                    messages.value.push({ role: 'assistant', content: data.message });
                    if (data.loan_ready) {
                        loanReady.value = true;
                        collected.value = data.collected_data;
                    }
                } catch (e) {
                    error.value = (e.response && e.response.data && e.response.data.error)
                        ? e.response.data.error : 'Something went wrong. Please try again.';
                } finally {
                    isTyping.value = false;
                    await scroll();
                    if (!loanReady.value && inputEl.value) inputEl.value.focus();
                }
            }

            async function createLoan() {
                error.value = '';
                var pw  = pwEl.value  ? pwEl.value.value  : '';
                var pw2 = pw2El.value ? pw2El.value.value : '';
                if (pw.length < 8)  { error.value = 'Password must be at least 8 characters'; return; }
                if (pw !== pw2)     { error.value = 'Passwords do not match'; return; }

                submitting.value = true;
                try {
                    var d = Object.assign({}, collected.value);
                    if (!d.exchange_date) d.exchange_date = null;
                    if (!d.other_dob)     d.other_dob     = null;
                    if (!d.your_address_2) d.your_address_2 = null;

                    await axios.post(BASE + '?action=create', Object.assign(d, {
                        your_password: pw,
                        terms_accepted: true,
                    }));

                    submitted.value = true;
                    messages.value.push({
                        role: 'assistant',
                        content: 'Your loan has been created!\n\nWe\'ve sent **' + d.other_first_name + '** an invite at **' + d.other_email + '**. Once they join Chipkie, your loan will be fully active.',
                    });
                    await scroll();
                } catch (e) {
                    var data = e.response && e.response.data;
                    if (data && data.errors) {
                        var first = Object.values(data.errors)[0];
                        error.value = Array.isArray(first) ? first[0] : first;
                    } else {
                        error.value = (data && data.message) ? data.message : 'Something went wrong. Please try again.';
                    }
                } finally {
                    submitting.value = false;
                }
            }

            onMounted(function () { if (inputEl.value) inputEl.value.focus(); });

            return { messages, text, isTyping, error, loanReady, submitted, submitting,
                     messagesEl, inputEl, pwEl, pw2El, md, send, createLoan };
        }
    }).mount('#app');
})();
</script>
</body>
</html>
