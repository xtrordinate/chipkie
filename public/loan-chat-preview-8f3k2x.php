<?php
/**
 * Chipkie Loan Chat — Preview / Test Page
 * Hidden URL: /loan-chat-preview-8f3k2x.php
 *
 * Self-contained: no Laravel, no Vite, no Composer.
 * Reads ANTHROPIC_API_KEY from the server environment.
 * Does NOT create real loans — shows collected data at the end.
 */

define('ANTHROPIC_KEY', getenv('ANTHROPIC_API_KEY') ?: '');
define('TODAY',     date('d/m/Y'));
define('TODAY_ISO', date('Y-m-d'));

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
        $toolBlock = $textBlock = null;
        foreach ($content as $block) {
            if ($block['type'] === 'tool_use') $toolBlock = $block;
            if ($block['type'] === 'text')     $textBlock = $block;
        }
        if ($toolBlock && $toolBlock['name'] === 'create_loan') {
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
        if ($block['type'] === 'text') { $textBlock = $block; break; }
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
        'system'      => systemPrompt(),
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

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['error' => 'Network error: ' . $curlErr];
    if ($status !== 200) return ['error' => 'Claude API returned HTTP ' . $status . ': ' . $body];

    $decoded = json_decode($body, true);
    return $decoded ?? ['error' => 'Invalid JSON from API'];
}

function systemPrompt(): string
{
    $today = TODAY;
    return <<<PROMPT
You are Chipkie's friendly loan assistant. Chipkie helps people create personal loan agreements between friends and family.

Today's date is {$today}.

Your task: guide the user through setting up a loan agreement via natural conversation. Collect the information below. Be warm, concise, and use **bold** for key numbers and terms.

━━━ LOAN DETAILS ━━━
• Role — lender (giving money) or borrower (receiving money)?
• Loan type — what is it for? (car, renovation, wedding, holiday, business, personal, etc.)
• Loan name — a friendly name both parties will recognise (e.g. "Sarah's Car Loan")
• Amount — must be greater than \$0
• Repayment frequency — Weekly, Fortnightly, or Monthly
• Duration — how long does the loan run? Convert to number of instalments based on frequency
• Interest rate — annual %, or 0 for interest-free. Calculate and show the repayment amount before asking about interest
• Has the money already been exchanged? If yes, on what date? (exchange date must be today or in the past — never future)
• Repayment start date — must be today ({$today}) or a future date. Reject past dates firmly and explain why

━━━ INITIATOR'S DETAILS (the person chatting) ━━━
• First name and last name
• Email address
• Date of birth (DD/MM/YYYY — must be in the past)
• Country
• Street address, suburb/city, state/province/region, postcode
• Phone number

━━━ OTHER PARTY'S DETAILS ━━━
• First name and last name
• Email address
• Date of birth (optional)
• State or region
• Phone number

━━━ PLAN & ADD-ONS ━━━
• Plan: Free (free forever, core features) | Premium (\$9.90/mo, tracking & reminders) | Premium Plus (\$5.40/mo, includes legal contract)
• Contract add-on: only ask if they didn't choose Premium Plus — legal contract for \$14.95 one-off?
• Extra signers: any additional signatories beyond the two main parties?
• Terms: confirm they agree to Chipkie's terms and conditions

━━━ RULES ━━━
• Collect naturally — group related questions, don't follow the list rigidly
• Use the user's first name once you know it
• If a country looks misspelled, gently check: "Did you mean [country]?"
• Show dates to the user as DD/MM/YYYY; pass them to create_loan as YYYY-MM-DD
• Never invent or assume values — only use what the user explicitly confirms
• Present a clear bullet-point summary of ALL details and get explicit confirmation before calling create_loan
• Only call create_loan after the user says yes to the summary
PROMPT;
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
                'role'                => ['type'=>'string','enum'=>['Lender','Borrower']],
                'loan_type'           => ['type'=>'string'],
                'loan_name'           => ['type'=>'string'],
                'amount'              => ['type'=>'number'],
                'frequency'           => ['type'=>'string','enum'=>['Weekly','Fortnightly','Monthly']],
                'instalments'         => ['type'=>'integer','minimum'=>1],
                'interest_rate'       => ['type'=>'number','minimum'=>0],
                'money_received'      => ['type'=>'boolean'],
                'exchange_date'       => ['type'=>'string','description'=>'YYYY-MM-DD or empty string'],
                'start_date'          => ['type'=>'string','description'=>'YYYY-MM-DD'],
                'your_first_name'     => ['type'=>'string'],
                'your_last_name'      => ['type'=>'string'],
                'your_email'          => ['type'=>'string'],
                'your_dob'            => ['type'=>'string','description'=>'YYYY-MM-DD'],
                'your_country'        => ['type'=>'string'],
                'your_street_address' => ['type'=>'string'],
                'your_address_2'      => ['type'=>'string','description'=>'Optional unit/apartment'],
                'your_suburb'         => ['type'=>'string'],
                'your_state'          => ['type'=>'string'],
                'your_postcode'       => ['type'=>'string'],
                'your_phone'          => ['type'=>'string'],
                'other_first_name'    => ['type'=>'string'],
                'other_last_name'     => ['type'=>'string'],
                'other_email'         => ['type'=>'string'],
                'other_dob'           => ['type'=>'string','description'=>'YYYY-MM-DD or empty string'],
                'other_state'         => ['type'=>'string'],
                'other_phone'         => ['type'=>'string'],
                'extra_signers'       => ['type'=>'boolean'],
                'plan'                => ['type'=>'string','enum'=>['Free','Premium','Premium Plus']],
                'contract_add_on'     => ['type'=>'boolean'],
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
<script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@keyframes bounce {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-5px); }
}
</style>
</head>
<body class="h-full font-sans antialiased bg-gray-50">

<div id="app">
    <div class="flex flex-col h-screen bg-gray-50 max-w-lg mx-auto">

        <!-- Header -->
        <div class="bg-[#004053] text-white px-5 py-4 flex items-center gap-3 shadow-md flex-shrink-0">
            <div class="w-10 h-10 rounded-full bg-[#007c89] flex items-center justify-center font-bold text-lg select-none">C</div>
            <div>
                <h1 class="font-bold text-base leading-none">Chipkie</h1>
                <p class="text-xs text-[#6dc4bc] mt-0.5">Loan Assistant</p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <span class="text-[10px] bg-yellow-400 text-yellow-900 font-bold px-2 py-0.5 rounded-full select-none">PREVIEW</span>
            </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-5 space-y-3">
            <div
                v-for="(msg, i) in messages"
                :key="i"
                :class="msg.role === 'assistant' ? 'flex items-end gap-2' : 'flex justify-end'"
            >
                <div v-if="msg.role === 'assistant'"
                    class="w-7 h-7 rounded-full bg-[#007c89] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">C</div>
                <div v-if="msg.role === 'assistant'"
                    class="bg-white text-[#004053] rounded-2xl rounded-bl-sm px-4 py-2.5 max-w-[80%] shadow-sm text-sm leading-relaxed"
                    v-html="renderMarkdown(msg.content)" />
                <div v-else
                    class="bg-[#007c89] text-white rounded-2xl rounded-br-sm px-4 py-2.5 max-w-[75%] shadow-sm text-sm leading-relaxed">
                    {{ msg.content }}
                </div>
            </div>

            <!-- Typing indicator -->
            <div v-if="isTyping" class="flex items-end gap-2">
                <div class="w-7 h-7 rounded-full bg-[#007c89] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">C</div>
                <div class="bg-white rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                    <div class="flex gap-1 items-center">
                        <span class="w-2 h-2 bg-gray-300 rounded-full" style="animation:bounce 1.2s infinite 0ms"></span>
                        <span class="w-2 h-2 bg-gray-300 rounded-full" style="animation:bounce 1.2s infinite 200ms"></span>
                        <span class="w-2 h-2 bg-gray-300 rounded-full" style="animation:bounce 1.2s infinite 400ms"></span>
                    </div>
                </div>
            </div>

            <!-- Collected data panel — shown instead of real loan creation -->
            <div v-if="loanReady && collectedData" class="mx-1 mt-2 bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-xs space-y-1">
                <p class="font-bold text-emerald-700 mb-2">Preview complete — data Claude would submit to create the loan:</p>
                <div v-for="(val, key) in collectedData" :key="key" class="flex gap-2 py-0.5 border-b border-emerald-100 last:border-0">
                    <span class="font-mono text-emerald-600 w-40 flex-shrink-0">{{ key }}</span>
                    <span class="text-emerald-900 break-all">{{ String(val) }}</span>
                </div>
            </div>
        </div>

        <!-- Bottom area -->
        <div class="bg-white border-t border-gray-200 px-4 py-3 flex-shrink-0">
            <p v-if="error" class="text-red-500 text-xs mb-2 px-1">{{ error }}</p>

            <!-- Chat input -->
            <div v-if="!loanReady" class="flex gap-2">
                <input
                    ref="inputRef"
                    v-model="inputValue"
                    type="text"
                    placeholder="Type your answer…"
                    :disabled="isTyping"
                    autocomplete="off"
                    @keydown.enter.prevent="sendMessage"
                    class="flex-1 border border-gray-200 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#007c89] focus:border-transparent disabled:opacity-50 disabled:bg-gray-50"
                />
                <button
                    type="button"
                    :disabled="isTyping || !inputValue.trim()"
                    @click="sendMessage"
                    class="w-10 h-10 bg-[#007c89] text-white rounded-full flex items-center justify-center hover:bg-[#004053] transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-lg"
                >&#10148;</button>
            </div>

            <!-- Done -->
            <div v-else class="text-center py-1 text-xs text-gray-400">
                Preview only — no loan was created. Reload to start again.
            </div>
        </div>

    </div>
</div>

<script>
const { createApp, ref, onMounted } = Vue

const OPENING = "Welcome to **Chipkie** — I'll help you set up your personal loan agreement.\n\nLet's start: are you the **lender** (you're giving the money) or the **borrower** (you're receiving it)?"

createApp({
    setup() {
        const messages          = ref([{ role: 'assistant', content: OPENING }])
        const inputValue        = ref('')
        const isTyping          = ref(false)
        const error             = ref('')
        const loanReady         = ref(false)
        const collectedData     = ref(null)
        const messagesContainer = ref(null)
        const inputRef          = ref(null)

        function renderMarkdown(text) {
            if (!text) return ''
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>')
        }

        function scrollToBottom() {
            return new Promise(resolve => setTimeout(() => {
                if (messagesContainer.value)
                    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
                resolve()
            }, 0))
        }

        async function sendMessage() {
            const text = inputValue.value.trim()
            if (!text || isTyping.value) return

            error.value = ''
            inputValue.value = ''
            messages.value.push({ role: 'user', content: text })
            await scrollToBottom()

            isTyping.value = true
            try {
                const { data } = await axios.post(window.location.pathname, {
                    messages: messages.value,
                })
                messages.value.push({ role: 'assistant', content: data.message })
                if (data.loan_ready) {
                    loanReady.value     = true
                    collectedData.value = data.collected_data
                }
            } catch (e) {
                error.value = e.response?.data?.error ?? 'Something went wrong. Please try again.'
            } finally {
                isTyping.value = false
                await scrollToBottom()
                if (!loanReady.value) inputRef.value?.focus()
            }
        }

        onMounted(() => inputRef.value?.focus())

        return {
            messages, inputValue, isTyping, error,
            loanReady, collectedData,
            messagesContainer, inputRef,
            renderMarkdown, sendMessage,
        }
    }
}).mount('#app')
</script>

</body>
</html>
