<template>
    <div class="flex flex-col h-screen bg-gray-50 max-w-lg mx-auto">

        <!-- Header -->
        <div class="bg-[#004053] text-white px-5 py-4 flex items-center gap-3 shadow-md flex-shrink-0">
            <div class="w-10 h-10 rounded-full bg-[#007c89] flex items-center justify-center font-bold text-lg select-none">
                C
            </div>
            <div>
                <h1 class="font-bold text-base leading-none">Chipkie</h1>
                <p class="text-xs text-[#6dc4bc] mt-0.5">Loan Assistant</p>
            </div>
        </div>

        <!-- Messages area -->
        <div
            ref="messagesContainer"
            class="flex-1 overflow-y-auto px-4 py-5 space-y-3"
        >
            <TransitionGroup name="message">
                <div
                    v-for="(msg, i) in messages"
                    :key="i"
                    :class="msg.from === 'bot' ? 'flex items-end gap-2' : 'flex justify-end'"
                >
                    <!-- Bot avatar -->
                    <div
                        v-if="msg.from === 'bot'"
                        class="w-7 h-7 rounded-full bg-[#007c89] flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                    >
                        C
                    </div>

                    <!-- Bot bubble -->
                    <div
                        v-if="msg.from === 'bot'"
                        class="bg-white text-[#004053] rounded-2xl rounded-bl-sm px-4 py-2.5 max-w-[75%] shadow-sm text-sm leading-relaxed"
                        v-html="renderMarkdown(msg.text)"
                    />

                    <!-- User bubble -->
                    <div
                        v-else
                        class="bg-[#007c89] text-white rounded-2xl rounded-br-sm px-4 py-2.5 max-w-[75%] shadow-sm text-sm leading-relaxed"
                    >
                        {{ msg.text }}
                    </div>
                </div>
            </TransitionGroup>

            <!-- Typing indicator -->
            <div v-if="isTyping" class="flex items-end gap-2">
                <div class="w-7 h-7 rounded-full bg-[#007c89] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    C
                </div>
                <div class="bg-white rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                    <div class="flex gap-1 items-center">
                        <span class="w-2 h-2 bg-gray-300 rounded-full inline-block" style="animation: bounce 1.2s infinite 0ms"></span>
                        <span class="w-2 h-2 bg-gray-300 rounded-full inline-block" style="animation: bounce 1.2s infinite 200ms"></span>
                        <span class="w-2 h-2 bg-gray-300 rounded-full inline-block" style="animation: bounce 1.2s infinite 400ms"></span>
                    </div>
                </div>
            </div>

            <!-- Loan summary card (shown at confirmation step) -->
            <Transition name="message">
                <div v-if="showSummary" class="mx-1">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-sm">
                        <div class="bg-[#004053] text-white px-4 py-3 font-semibold text-xs uppercase tracking-wide">
                            Loan Summary
                        </div>
                        <div class="divide-y divide-gray-50">
                            <SummaryRow label="You are the" :value="answers.role" />
                            <SummaryRow label="Loan amount" :value="formatCurrency(answers.amount)" />
                            <SummaryRow label="Your name" :value="answers.yourName" />
                            <SummaryRow label="Your email" :value="answers.yourEmail" />
                            <SummaryRow
                                :label="answers.role === 'Lender' ? 'Borrower name' : 'Lender name'"
                                :value="answers.otherName"
                            />
                            <SummaryRow
                                :label="answers.role === 'Lender' ? 'Borrower email' : 'Lender email'"
                                :value="answers.otherEmail"
                            />
                            <SummaryRow label="Repayment frequency" :value="answers.frequency" />
                            <SummaryRow label="Number of repayments" :value="answers.instalments" />
                            <SummaryRow
                                label="Repayment amount"
                                :value="formatCurrency(repaymentAmount)"
                                highlight
                            />
                            <SummaryRow
                                label="Interest rate"
                                :value="answers.interestRate === '0' ? 'Interest-free' : `${answers.interestRate}% p.a.`"
                            />
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Input area -->
        <div class="bg-white border-t border-gray-200 px-4 py-3 flex-shrink-0">

            <!-- Validation error -->
            <p v-if="validationError" class="text-red-500 text-xs mb-2 px-1">
                {{ validationError }}
            </p>

            <!-- Choice buttons -->
            <div
                v-if="currentStep && currentStep.type === 'choice' && !isDone && !isSubmitted"
                class="flex flex-wrap gap-2 justify-center"
            >
                <button
                    v-for="choice in currentStep.choices"
                    :key="choice"
                    :disabled="isTyping"
                    @click="handleChoice(choice)"
                    class="px-5 py-2 bg-[#007c89] text-white rounded-full text-sm font-medium
                           hover:bg-[#004053] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ choice }}
                </button>
            </div>

            <!-- Text / number / email input -->
            <div
                v-else-if="currentStep && !isDone && !isSubmitted"
                class="flex gap-2"
            >
                <input
                    ref="inputRef"
                    v-model="inputValue"
                    :type="inputType"
                    :inputmode="currentStep.type === 'currency' || currentStep.type === 'number' ? 'decimal' : undefined"
                    :placeholder="currentStep.placeholder"
                    :disabled="isTyping"
                    @keydown.enter.prevent="handleTextSubmit"
                    @keyup.enter.prevent="handleTextSubmit"
                    class="flex-1 border border-gray-200 rounded-full px-4 py-2.5 text-sm
                           focus:outline-none focus:ring-2 focus:ring-[#007c89] focus:border-transparent
                           disabled:opacity-50 disabled:bg-gray-50"
                />
                <button
                    type="button"
                    :disabled="isTyping"
                    @click="handleTextSubmit"
                    class="w-10 h-10 bg-[#007c89] text-white rounded-full flex items-center justify-center
                           hover:bg-[#004053] transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-lg"
                >
                    &#10148;
                </button>
            </div>

            <!-- Confirm / Start over -->
            <div v-else-if="isDone && !isSubmitted" class="flex gap-2">
                <button
                    @click="startOver"
                    class="flex-1 py-2.5 border border-gray-200 text-gray-500 rounded-full text-sm font-medium
                           hover:bg-gray-50 transition-colors"
                >
                    Start over
                </button>
                <button
                    :disabled="isSubmitting"
                    @click="submitLoan"
                    class="flex-1 py-2.5 bg-[#007c89] text-white rounded-full text-sm font-semibold
                           hover:bg-[#004053] transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    {{ isSubmitting ? 'Creating…' : 'Create Loan' }}
                </button>
            </div>

            <!-- Post-submit -->
            <div v-else-if="isSubmitted" class="text-center py-1">
                <a
                    href="/"
                    class="text-[#007c89] text-sm font-medium hover:underline"
                >
                    Go to dashboard &rarr;
                </a>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, nextTick, onMounted, defineComponent, h } from 'vue'
import axios from 'axios'

// ─── Summary row sub-component ───────────────────────────────────────────────
const SummaryRow = defineComponent({
    props: { label: String, value: [String, Number], highlight: Boolean },
    setup(props) {
        return () => h('div', { class: 'flex justify-between items-center px-4 py-2.5' }, [
            h('span', { class: 'text-gray-400 text-xs' }, props.label),
            h('span', {
                class: props.highlight
                    ? 'font-semibold text-[#007c89] text-sm'
                    : 'font-medium text-[#004053] text-sm'
            }, props.value ?? '—'),
        ])
    },
})

// ─── Conversation steps ───────────────────────────────────────────────────────
const STEPS = [
    {
        id: 'role',
        question: () =>
            "Hi! I'm **Chipkie** — your personal loan assistant. Let's set up a loan between friends or family.\n\nAre you the **lender** (giving money) or the **borrower** (receiving money)?",
        type: 'choice',
        choices: ['Lender', 'Borrower'],
        field: 'role',
    },
    {
        id: 'amount',
        question: (a) =>
            `Got it, you're the **${a.role.toLowerCase()}**. How much money is this loan for? (enter just the number, e.g. 500)`,
        type: 'currency',
        field: 'amount',
        placeholder: 'e.g. 500',
        validate: (v) => (!isNaN(v) && parseFloat(v) > 0) ? null : 'Please enter a valid amount greater than 0',
    },
    {
        id: 'yourName',
        question: (a) =>
            `A **${formatCurrencyRaw(a.amount)}** loan — nice! What's your full name?`,
        type: 'text',
        field: 'yourName',
        placeholder: 'Your full name',
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter your full name',
    },
    {
        id: 'yourEmail',
        question: (a) =>
            `Nice to meet you, **${firstName(a.yourName)}**! What's your email address?`,
        type: 'email',
        field: 'yourEmail',
        placeholder: 'you@example.com',
        validate: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Please enter a valid email address',
    },
    {
        id: 'otherName',
        question: (a) => a.role === 'Lender'
            ? `Who are you lending **${formatCurrencyRaw(a.amount)}** to? What's their full name?`
            : `Who are you borrowing **${formatCurrencyRaw(a.amount)}** from? What's their full name?`,
        type: 'text',
        field: 'otherName',
        placeholder: 'Their full name',
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter their full name',
    },
    {
        id: 'otherEmail',
        question: (a) =>
            `What's **${firstName(a.otherName)}'s** email address? We'll send them an invitation to join Chipkie.`,
        type: 'email',
        field: 'otherEmail',
        placeholder: 'them@example.com',
        validate: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Please enter a valid email address',
    },
    {
        id: 'frequency',
        question: () => 'How often should repayments be made?',
        type: 'choice',
        choices: ['Weekly', 'Fortnightly', 'Monthly'],
        field: 'frequency',
    },
    {
        id: 'instalments',
        question: (a) =>
            `How many **${a.frequency.toLowerCase()}** repayments will there be in total?`,
        type: 'number',
        field: 'instalments',
        placeholder: 'e.g. 12',
        validate: (v) => (!isNaN(v) && parseInt(v) >= 1) ? null : 'Please enter a valid number of repayments (minimum 1)',
    },
    {
        id: 'interestRate',
        question: () => "What's the annual interest rate? Enter **0** for interest-free.",
        type: 'number',
        field: 'interestRate',
        placeholder: '0',
        validate: (v) => (!isNaN(v) && parseFloat(v) >= 0) ? null : 'Please enter 0 or a positive interest rate',
    },
]

// ─── Helpers ──────────────────────────────────────────────────────────────────
function firstName(name) {
    return (name || '').split(' ')[0]
}

function formatCurrencyRaw(val) {
    if (!val) return '$0'
    return '$' + Number(val).toLocaleString('en-AU', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function formatCurrency(val) {
    if (!val) return '—'
    return '$' + Number(val).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function renderMarkdown(text) {
    if (!text) return ''
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>')
}

// ─── State ────────────────────────────────────────────────────────────────────
const messages = ref([])
const answers = reactive({})
const inputValue = ref('')
const validationError = ref('')
const isTyping = ref(false)
const isDone = ref(false)
const showSummary = ref(false)
const isSubmitting = ref(false)
const isSubmitted = ref(false)
const currentStepIndex = ref(0)

const messagesContainer = ref(null)
const inputRef = ref(null)

// ─── Computed ─────────────────────────────────────────────────────────────────
const currentStep = computed(() => {
    if (isDone.value) return null
    return STEPS[currentStepIndex.value] ?? null
})

const inputType = computed(() => {
    const t = currentStep.value?.type
    if (t === 'email') return 'email'
    return 'text'
})

const repaymentAmount = computed(() => {
    const P = parseFloat(answers.amount)
    const n = parseInt(answers.instalments)
    const annualRate = parseFloat(answers.interestRate)
    if (!P || !n) return 0
    if (!annualRate || annualRate === 0) return (P / n).toFixed(2)

    const periodsPerYear = answers.frequency === 'Weekly' ? 52
        : answers.frequency === 'Fortnightly' ? 26 : 12
    const r = annualRate / 100 / periodsPerYear
    const payment = P * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1)
    return payment.toFixed(2)
})

// ─── Chat engine ──────────────────────────────────────────────────────────────
async function scrollToBottom() {
    await nextTick()
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
}

async function showBotMessage(text, delay = 700) {
    isTyping.value = true
    await scrollToBottom()
    await new Promise(r => setTimeout(r, delay))
    isTyping.value = false
    messages.value.push({ from: 'bot', text })
    await scrollToBottom()
}

async function advanceToStep(index) {
    if (index >= STEPS.length) {
        isDone.value = true
        showSummary.value = true
        await showBotMessage(
            "Here's your loan summary. Everything look good?\n\nHit **Create Loan** to send an invitation to " +
            `**${firstName(answers.otherName)}** and get things started!`
        )
        return
    }

    currentStepIndex.value = index
    const step = STEPS[index]
    const question = typeof step.question === 'function' ? step.question(answers) : step.question
    await showBotMessage(question)

    if (step.type !== 'choice') {
        await nextTick()
        inputRef.value?.focus()
    }
}

async function handleChoice(choice) {
    if (isTyping.value) return
    const step = currentStep.value
    if (!step) return
    answers[step.field] = choice
    messages.value.push({ from: 'user', text: choice })
    inputValue.value = ''
    validationError.value = ''
    await scrollToBottom()
    await advanceToStep(currentStepIndex.value + 1)
}

async function handleTextSubmit() {
    if (isTyping.value) return
    const step = currentStep.value
    if (!step) return

    const value = String(inputValue.value ?? '').trim()

    if (step.validate) {
        const err = step.validate(value)
        if (err) {
            validationError.value = err
            return
        }
    } else if (!value) {
        validationError.value = 'Please enter a value'
        return
    }

    validationError.value = ''
    answers[step.field] = value

    const displayValue = step.type === 'currency'
        ? formatCurrencyRaw(value)
        : value
    messages.value.push({ from: 'user', text: displayValue })
    inputValue.value = ''
    await scrollToBottom()
    await advanceToStep(currentStepIndex.value + 1)
}

async function submitLoan() {
    isSubmitting.value = true
    validationError.value = ''
    try {
        await axios.post('/loans/chat', {
            role: answers.role,
            amount: answers.amount,
            your_name: answers.yourName,
            your_email: answers.yourEmail,
            other_name: answers.otherName,
            other_email: answers.otherEmail,
            frequency: answers.frequency,
            instalments: answers.instalments,
            interest_rate: answers.interestRate,
        })
        isSubmitted.value = true
        await showBotMessage(
            `Your loan has been created! 🎉\n\nWe've sent an invitation to **${firstName(answers.otherName)}** ` +
            `at **${answers.otherEmail}**. Once they accept and join Chipkie, your loan will be fully active.\n\n` +
            `Check your email for next steps.`,
            500
        )
    } catch (err) {
        const msg = err.response?.data?.message || 'Something went wrong. Please try again.'
        validationError.value = msg
    } finally {
        isSubmitting.value = false
    }
}

function startOver() {
    messages.value = []
    Object.keys(answers).forEach(k => delete answers[k])
    inputValue.value = ''
    validationError.value = ''
    isDone.value = false
    showSummary.value = false
    isSubmitted.value = false
    currentStepIndex.value = 0
    advanceToStep(0)
}

onMounted(() => {
    advanceToStep(0)
})
</script>

<style scoped>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.message-enter-active {
    transition: all 0.3s ease-out;
}
.message-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
</style>
