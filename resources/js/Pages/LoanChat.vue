<template>
    <div class="flex flex-col h-screen bg-gray-50 max-w-lg mx-auto">

        <!-- Header -->
        <div class="bg-[#004053] text-white px-5 py-4 flex items-center gap-3 shadow-md flex-shrink-0">
            <div class="w-10 h-10 rounded-full bg-[#007c89] flex items-center justify-center font-bold text-lg select-none">C</div>
            <div>
                <h1 class="font-bold text-base leading-none">Chipkie</h1>
                <p class="text-xs text-[#6dc4bc] mt-0.5">Loan Assistant</p>
            </div>
            <div class="ml-auto text-[10px] text-[#6dc4bc] opacity-60 select-none">v8</div>
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
        </div>

        <!-- Bottom area -->
        <div class="bg-white border-t border-gray-200 px-4 py-3 flex-shrink-0">
            <p v-if="error" class="text-red-500 text-xs mb-2 px-1">{{ error }}</p>

            <!-- Chat input -->
            <div v-if="!loanReady && !isSubmitted" class="flex gap-2">
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

            <!-- Password + create loan (after Claude collects everything) -->
            <div v-else-if="loanReady && !isSubmitted" class="space-y-2">
                <p class="text-xs text-gray-400 px-1">Set your Chipkie password to create the loan</p>
                <input
                    v-model="pwSetup"
                    type="password"
                    placeholder="Password (min 8 characters)"
                    autocomplete="new-password"
                    class="w-full border border-gray-200 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#007c89]"
                />
                <input
                    v-model="pwSetupConfirm"
                    type="password"
                    placeholder="Confirm password"
                    autocomplete="new-password"
                    class="w-full border border-gray-200 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#007c89]"
                />
                <button
                    type="button"
                    :disabled="isSubmitting"
                    @click="submitLoan"
                    class="w-full py-2.5 bg-[#007c89] text-white rounded-full text-sm font-semibold hover:bg-[#004053] transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >{{ isSubmitting ? 'Creating loan…' : 'Create Loan' }}</button>
            </div>

            <!-- Post-submit -->
            <div v-else class="text-center py-1">
                <a href="/" class="text-[#007c89] text-sm font-medium hover:underline">Go to dashboard &rarr;</a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const OPENING = "Welcome to **Chipkie** — I'll help you set up your personal loan agreement.\n\nLet's start: are you the **lender** (you're giving the money) or the **borrower** (you're receiving it)?"

// UI state
const messages = ref([{ role: 'assistant', content: OPENING }])
const inputValue = ref('')
const isTyping = ref(false)
const error = ref('')

// Loan-ready state (Claude has finished collecting)
const loanReady = ref(false)
const collectedData = ref(null)

// Password + submission
const pwSetup = ref('')
const pwSetupConfirm = ref('')
const isSubmitting = ref(false)
const isSubmitted = ref(false)

const messagesContainer = ref(null)
const inputRef = ref(null)

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
        const { data } = await axios.post('/loans/chat/message', {
            messages: messages.value,
        })

        messages.value.push({ role: 'assistant', content: data.message })

        if (data.loan_ready) {
            loanReady.value = true
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

async function submitLoan() {
    error.value = ''
    const pw = pwSetup.value
    const pwc = pwSetupConfirm.value
    if (pw.length < 8) { error.value = 'Password must be at least 8 characters'; return }
    if (pw !== pwc)    { error.value = 'Passwords do not match'; return }

    isSubmitting.value = true
    try {
        const d = { ...collectedData.value }
        // Coerce empty strings to null for nullable date fields
        if (!d.exchange_date) d.exchange_date = null
        if (!d.other_dob)     d.other_dob = null
        if (!d.your_address_2) d.your_address_2 = null

        await axios.post('/loans/chat', {
            ...d,
            your_password: pw,
            terms_accepted: true,
        })

        isSubmitted.value = true
        messages.value.push({
            role: 'assistant',
            content: `Your loan has been created! 🎉\n\nWe've sent **${d.other_first_name}** an invite at **${d.other_email}**. Once they join Chipkie, your loan will be fully active.`,
        })
        await scrollToBottom()
    } catch (e) {
        const data = e.response?.data
        if (data?.errors) {
            const first = Object.values(data.errors)[0]
            error.value = Array.isArray(first) ? first[0] : first
        } else {
            error.value = data?.message ?? 'Something went wrong. Please try again.'
        }
    } finally {
        isSubmitting.value = false
    }
}

onMounted(() => { inputRef.value?.focus() })
</script>

<style scoped>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-5px); }
}
</style>
