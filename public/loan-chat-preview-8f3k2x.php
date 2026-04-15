<?php
/**
 * Chipkie Loan Chat — Preview / Test Page
 * Hidden URL: /loan-chat-preview-8f3k2x.php
 *
 * Self-contained: no Laravel, no Vite, no Composer.
 * Reads ANTHROPIC_API_KEY from the server environment.
 * Does NOT create real loans — shows collected data at the end.
 */

define('ANTHROPIC_KEY', (string) (getenv('ANTHROPIC_API_KEY') ?: ''));
define('TODAY',     date('d/m/Y'));

// ─── POST: API proxy ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['messages'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    if (!ANTHROPIC_KEY) {
        http_response_code(503);
        echo json_encode(['error' => 'ANTHROPIC_API_KEY is not set on this server.']);
        exit;
    }

    $result = callClaude($input['messages']);

    if (isset($result['error'])) {
        http_response_code(503);
        echo json_encode(['error' => $result['error']]);
        exit;
    }

    $stopReason = $result['stop_reason'] ?? '';
    $content    = $result['content']    ?? [];

    if ($stopReason === 'tool_use') {
        $toolBlock = null;
        $textBlock = null;
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_use') $toolBlock = $block;
            if (($block['type'] ?? '') === 'text')     $textBlock = $block;
        }
        if ($toolBlock && ($toolBlock['name'] ?? '') === 'create_loan') {
            echo json_encode([
                'message'        => $textBlock['text'] ?? 'All done! See the collected data below.',
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

// ─── Helpers ──────────────────────────────────────────────────────────────────
function callClaude(array $messages): array
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
            'x-api-key: ' . ANTHROPIC_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $body    = curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr)    return ['error' => 'Network error: ' . $curlErr];
    if ($status !== 200) return ['error' => 'Claude API returned HTTP ' . $status . ': ' . substr($body, 0, 200)];

    $decoded = json_decode($body, true);
    return $decoded ?? ['error' => 'Invalid JSON from API'];
}

function buildSystemPrompt(): string
{
    $today = TODAY;
    $lines = [
        'You are Chipkie\'s friendly loan assistant. Chipkie helps people create personal loan agreements between friends and family.',
        '',
        'Today\'s date is ' . $today . '.',
        '',
        'Your task: guide the user through setting up a loan agreement via natural conversation. Collect the information below. Be warm, concise, and use **bold** for key numbers and terms.',
        '',
        '--- LOAN DETAILS ---',
        '* Role: lender (giving money) or borrower (receiving money)?',
        '* Loan type: what is it for? (car, renovation, wedding, holiday, business, personal, etc.)',
        '* Loan name: a friendly name both parties will recognise (e.g. "Sarah\'s Car Loan")',
        '* Amount: must be greater than $0',
        '* Repayment frequency: Weekly, Fortnightly, or Monthly',
        '* Duration: how long does the loan run? Convert to number of instalments based on frequency',
        '* Interest rate: annual %, or 0 for interest-free. Calculate and show the repayment amount before asking about interest',
        '* Has the money already been exchanged? If yes, on what date? (exchange date must be today or in the past — never future)',
        '* Repayment start date: must be today (' . $today . ') or a future date. Reject past dates firmly and explain why',
        '',
        '--- INITIATOR DETAILS (the person chatting) ---',
        '* First name and last name',
        '* Email address',
        '* Date of birth (DD/MM/YYYY — must be in the past)',
        '* Country',
        '* Street address, suburb/city, state/province/region, postcode',
        '* Phone number',
        '',
        '--- OTHER PARTY DETAILS ---',
        '* First name and last name',
        '* Email address',
        '* Date of birth (optional)',
        '* State or region',
        '* Phone number',
        '',
        '--- PLAN & ADD-ONS ---',
        '* Plan: Free (free forever, core features) | Premium ($9.90/mo, tracking & reminders) | Premium Plus ($5.40/mo, includes legal contract)',
        '* Contract add-on: only ask if they did not choose Premium Plus — legal contract for $14.95 one-off?',
        '* Extra signers: any additional signatories beyond the two main parties?',
        '* Terms: confirm they agree to Chipkie\'s terms and conditions',
        '',
        '--- RULES ---',
        '* Collect naturally — group related questions, don\'t follow the list rigidly',
        '* Use the user\'s first name once you know it',
        '* If a country looks misspelled, gently check: "Did you mean [country]?"',
        '* Show dates to the user as DD/MM/YYYY; pass them to create_loan as YYYY-MM-DD',
        '* Never invent or assume values — only use what the user explicitly confirms',
        '* Present a clear bullet-point summary of ALL details and get explicit confirmation before calling create_loan',
        '* Only call create_loan after the user says yes to the summary',
    ];
    return implode("\n", $lines);
}

function createLoanTool(): array
{
    return [
        'name'         => 'create_loan',
        'description'  => 'Create the loan agreement. Call ONLY after presenting a full summary and receiving explicit user confirmation.',
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
                'role'                => ['type' => 'string', 'enum' => ['Lender', 'Borrower']],
                'loan_type'           => ['type' => 'string'],
                'loan_name'           => ['type' => 'string'],
                'amount'              => ['type' => 'number'],
                'frequency'           => ['type' => 'string', 'enum' => ['Weekly', 'Fortnightly', 'Monthly']],
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
                'your_address_2'      => ['type' => 'string', 'description' => 'Optional unit/apartment'],
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
                'plan'                => ['type' => 'string', 'enum' => ['Free', 'Premium', 'Premium Plus']],
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
<title>Chipkie — Loan Chat Preview</title>
<script src="https://cdn.jsdelivr.net/npm/vue@3.4.21/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.8/dist/axios.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.3/base.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: ui-sans-serif, system-ui, sans-serif; background: #f9fafb; }
.app { display: flex; flex-direction: column; height: 100vh; max-width: 32rem; margin: 0 auto; }
.header { background: #004053; color: #fff; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
.avatar { width: 2.5rem; height: 2.5rem; border-radius: 50%; background: #007c89; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
.avatar-sm { width: 1.75rem; height: 1.75rem; border-radius: 50%; background: #007c89; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.7rem; font-weight: 700; flex-shrink: 0; }
.header h1 { font-size: 0.95rem; font-weight: 700; line-height: 1; }
.header p { font-size: 0.7rem; color: #6dc4bc; margin-top: 2px; }
.badge { font-size: 0.65rem; background: #fbbf24; color: #78350f; font-weight: 700; padding: 2px 8px; border-radius: 999px; margin-left: auto; }
.messages { flex: 1; overflow-y: auto; padding: 1.25rem 1rem; display: flex; flex-direction: column; gap: 0.75rem; }
.msg-assistant { display: flex; align-items: flex-end; gap: 0.5rem; }
.msg-user { display: flex; justify-content: flex-end; }
.bubble-assistant { background: #fff; color: #004053; border-radius: 1rem 1rem 1rem 0.25rem; padding: 0.6rem 1rem; max-width: 80%; box-shadow: 0 1px 3px rgba(0,0,0,.08); font-size: 0.875rem; line-height: 1.6; }
.bubble-user { background: #007c89; color: #fff; border-radius: 1rem 1rem 0.25rem 1rem; padding: 0.6rem 1rem; max-width: 75%; box-shadow: 0 1px 3px rgba(0,0,0,.08); font-size: 0.875rem; line-height: 1.6; }
.typing { display: flex; align-items: flex-end; gap: 0.5rem; }
.typing-dots { background: #fff; border-radius: 1rem 1rem 1rem 0.25rem; padding: 0.75rem 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); display: flex; gap: 4px; align-items: center; }
.dot { width: 8px; height: 8px; background: #d1d5db; border-radius: 50%; }
@keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
.bottom { background: #fff; border-top: 1px solid #e5e7eb; padding: 0.75rem 1rem; flex-shrink: 0; }
.input-row { display: flex; gap: 0.5rem; }
.chat-input { flex: 1; border: 1px solid #e5e7eb; border-radius: 999px; padding: 0.6rem 1rem; font-size: 0.875rem; outline: none; }
.chat-input:focus { border-color: #007c89; box-shadow: 0 0 0 2px rgba(0,124,137,.2); }
.chat-input:disabled { opacity: .5; background: #f9fafb; }
.send-btn { width: 2.5rem; height: 2.5rem; border-radius: 50%; background: #007c89; color: #fff; border: none; cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.send-btn:hover { background: #004053; }
.send-btn:disabled { opacity: .5; cursor: not-allowed; }
.error { color: #ef4444; font-size: 0.75rem; margin-bottom: 0.5rem; padding: 0 0.25rem; }
.data-panel { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 1rem; padding: 1rem; font-size: 0.75rem; margin: 0.25rem; }
.data-panel h4 { font-weight: 700; color: #065f46; margin-bottom: 0.5rem; }
.data-row { display: flex; gap: 0.5rem; padding: 3px 0; border-bottom: 1px solid #d1fae5; }
.data-row:last-child { border-bottom: none; }
.data-key { font-family: monospace; color: #059669; width: 9rem; flex-shrink: 0; }
.data-val { color: #064e3b; word-break: break-all; }
.done-msg { text-align: center; padding: 0.25rem; font-size: 0.75rem; color: #9ca3af; }
</style>
</head>
<body>

<div id="app">
    <div class="app">

        <!-- Header -->
        <div class="header">
            <div class="avatar">C</div>
            <div>
                <h1>Chipkie</h1>
                <p>Loan Assistant</p>
            </div>
            <span class="badge">PREVIEW</span>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="messages">
            <template v-for="(msg, i) in messages" :key="i">
                <div v-if="msg.role === 'assistant'" class="msg-assistant">
                    <div class="avatar-sm">C</div>
                    <div class="bubble-assistant" v-html="renderMarkdown(msg.content)"></div>
                </div>
                <div v-else class="msg-user">
                    <div class="bubble-user">{{ msg.content }}</div>
                </div>
            </template>

            <!-- Typing indicator -->
            <div v-if="isTyping" class="typing">
                <div class="avatar-sm">C</div>
                <div class="typing-dots">
                    <div class="dot" style="animation:bounce 1.2s infinite 0ms"></div>
                    <div class="dot" style="animation:bounce 1.2s infinite 200ms"></div>
                    <div class="dot" style="animation:bounce 1.2s infinite 400ms"></div>
                </div>
            </div>

            <!-- Collected data — shown instead of real loan creation -->
            <div v-if="loanReady && collectedData" class="data-panel">
                <h4>Preview complete — data Claude would submit:</h4>
                <div v-for="(val, key) in collectedData" :key="key" class="data-row">
                    <span class="data-key">{{ key }}</span>
                    <span class="data-val">{{ String(val) }}</span>
                </div>
            </div>
        </div>

        <!-- Bottom -->
        <div class="bottom">
            <p v-if="error" class="error">{{ error }}</p>

            <div v-if="!loanReady" class="input-row">
                <input
                    ref="inputRef"
                    v-model="inputValue"
                    type="text"
                    placeholder="Type your answer…"
                    :disabled="isTyping"
                    autocomplete="off"
                    class="chat-input"
                    v-on:keydown.enter.prevent="sendMessage"
                />
                <button
                    type="button"
                    :disabled="isTyping || !inputValue.trim()"
                    class="send-btn"
                    v-on:click="sendMessage"
                >&#10148;</button>
            </div>

            <div v-else class="done-msg">
                Preview only — no loan was created. Reload to start again.
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    var app = Vue.createApp({
        setup: function () {
            var ref = Vue.ref;
            var onMounted = Vue.onMounted;

            var OPENING = "Welcome to **Chipkie** \u2014 I'll help you set up your personal loan agreement.\n\nLet's start: are you the **lender** (you're giving the money) or the **borrower** (you're receiving it)?";

            var messages          = ref([{ role: 'assistant', content: OPENING }]);
            var inputValue        = ref('');
            var isTyping          = ref(false);
            var error             = ref('');
            var loanReady         = ref(false);
            var collectedData     = ref(null);
            var messagesContainer = ref(null);
            var inputRef          = ref(null);

            function renderMarkdown(text) {
                if (!text) return '';
                return text
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/\n/g, '<br>');
            }

            function scrollToBottom() {
                return new Promise(function (resolve) {
                    setTimeout(function () {
                        if (messagesContainer.value) {
                            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
                        }
                        resolve();
                    }, 0);
                });
            }

            async function sendMessage() {
                var text = inputValue.value.trim();
                if (!text || isTyping.value) return;

                error.value = '';
                inputValue.value = '';
                messages.value.push({ role: 'user', content: text });
                await scrollToBottom();

                isTyping.value = true;
                try {
                    var response = await axios.post(window.location.pathname, {
                        messages: messages.value,
                    });
                    var data = response.data;
                    messages.value.push({ role: 'assistant', content: data.message });
                    if (data.loan_ready) {
                        loanReady.value     = true;
                        collectedData.value = data.collected_data;
                    }
                } catch (e) {
                    error.value = (e.response && e.response.data && e.response.data.error)
                        ? e.response.data.error
                        : 'Something went wrong. Please try again.';
                } finally {
                    isTyping.value = false;
                    await scrollToBottom();
                    if (!loanReady.value && inputRef.value) inputRef.value.focus();
                }
            }

            onMounted(function () {
                if (inputRef.value) inputRef.value.focus();
            });

            return {
                messages, inputValue, isTyping, error,
                loanReady, collectedData,
                messagesContainer, inputRef,
                renderMarkdown, sendMessage,
            };
        }
    });

    app.mount('#app');
})();
</script>

</body>
</html>
