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
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-5 space-y-3">
            <TransitionGroup name="message">
                <div
                    v-for="(msg, i) in messages"
                    :key="i"
                    :class="msg.from === 'bot' ? 'flex items-end gap-2' : 'flex justify-end'"
                >
                    <div
                        v-if="msg.from === 'bot'"
                        class="w-7 h-7 rounded-full bg-[#007c89] flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                    >C</div>
                    <div
                        v-if="msg.from === 'bot'"
                        class="bg-white text-[#004053] rounded-2xl rounded-bl-sm px-4 py-2.5 max-w-[75%] shadow-sm text-sm leading-relaxed"
                        v-html="renderMarkdown(msg.text)"
                    />
                    <div
                        v-else
                        class="bg-[#007c89] text-white rounded-2xl rounded-br-sm px-4 py-2.5 max-w-[75%] shadow-sm text-sm leading-relaxed"
                    >{{ msg.text }}</div>
                </div>
            </TransitionGroup>

            <!-- Typing indicator -->
            <div v-if="isTyping" class="flex items-end gap-2">
                <div class="w-7 h-7 rounded-full bg-[#007c89] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">C</div>
                <div class="bg-white rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                    <div class="flex gap-1 items-center">
                        <span class="w-2 h-2 bg-gray-300 rounded-full inline-block" style="animation: bounce 1.2s infinite 0ms"></span>
                        <span class="w-2 h-2 bg-gray-300 rounded-full inline-block" style="animation: bounce 1.2s infinite 200ms"></span>
                        <span class="w-2 h-2 bg-gray-300 rounded-full inline-block" style="animation: bounce 1.2s infinite 400ms"></span>
                    </div>
                </div>
            </div>

            <!-- Loan summary card -->
            <Transition name="message">
                <div v-if="showSummary && !isEarlyExit" class="mx-1">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-sm">
                        <div class="bg-[#004053] text-white px-4 py-3 font-semibold text-xs uppercase tracking-wide">
                            Loan Summary
                        </div>
                        <template v-for="section in summaryData" :key="section.title">
                            <div class="px-4 py-2 bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide border-t border-gray-100">
                                {{ section.title }}
                            </div>
                            <div class="divide-y divide-gray-50">
                                <SummaryRow
                                    v-for="row in section.rows"
                                    :key="row.label"
                                    :label="row.label"
                                    :value="row.value"
                                    :highlight="row.highlight"
                                />
                            </div>
                        </template>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Input area -->
        <div class="bg-white border-t border-gray-200 px-4 py-3 flex-shrink-0">
            <p v-if="validationError" class="text-red-500 text-xs mb-2 px-1">{{ validationError }}</p>

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
                    class="px-5 py-2 bg-[#007c89] text-white rounded-full text-sm font-medium hover:bg-[#004053] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >{{ choice }}</button>
            </div>

            <!-- Text / email / currency / number input (non-password) -->
            <div v-else-if="currentStep && currentStep.type !== 'password' && !isDone && !isSubmitted" class="space-y-2">
                <button
                    v-if="currentStep.optional"
                    type="button"
                    :disabled="isTyping"
                    @click="handleSkip"
                    class="w-full text-center text-xs text-gray-400 hover:text-gray-600 py-1 transition-colors disabled:opacity-50"
                >Skip</button>
                <div class="flex gap-2">
                    <input
                        ref="inputRef"
                        v-model="inputValue"
                        :type="currentStep.type === 'email' ? 'email' : 'text'"
                        :inputmode="currentStep.type === 'currency' || currentStep.type === 'number' ? 'decimal' : undefined"
                        :placeholder="currentStep.placeholder"
                        :disabled="isTyping"
                        autocomplete="off"
                        @keydown.enter.prevent="handleTextSubmit"
                        @keyup.enter="handleTextSubmit"
                        class="flex-1 border border-gray-200 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#007c89] focus:border-transparent disabled:opacity-50 disabled:bg-gray-50"
                    />
                    <button
                        type="button"
                        :disabled="isTyping"
                        @click="handleTextSubmit"
                        class="w-10 h-10 bg-[#007c89] text-white rounded-full flex items-center justify-center hover:bg-[#004053] transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-lg"
                    >&#10148;</button>
                </div>
            </div>

            <!-- Password input — completely separate element, never changes type -->
            <div v-else-if="currentStep && currentStep.type === 'password' && !isDone && !isSubmitted" class="space-y-2">
                <div class="flex gap-2">
                    <input
                        ref="pwInputRef"
                        v-model="pwValue"
                        type="password"
                        :placeholder="currentStep.placeholder"
                        :disabled="isTyping"
                        autocomplete="new-password"
                        @keydown.enter.prevent="handlePwSubmit"
                        @keyup.enter="handlePwSubmit"
                        class="flex-1 border border-gray-200 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#007c89] focus:border-transparent disabled:opacity-50 disabled:bg-gray-50"
                    />
                    <button
                        type="button"
                        :disabled="isTyping"
                        @click="handlePwSubmit"
                        class="w-10 h-10 bg-[#007c89] text-white rounded-full flex items-center justify-center hover:bg-[#004053] transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-lg"
                    >&#10148;</button>
                </div>
            </div>

            <!-- Summary confirmed: Start over + Create Loan -->
            <div v-else-if="isDone && !isEarlyExit && !isSubmitted" class="flex gap-2">
                <button
                    type="button"
                    @click="startOver"
                    class="flex-1 py-2.5 border border-gray-200 text-gray-500 rounded-full text-sm font-medium hover:bg-gray-50 transition-colors"
                >Start over</button>
                <button
                    type="button"
                    :disabled="isSubmitting"
                    @click="submitLoan"
                    class="flex-1 py-2.5 bg-[#007c89] text-white rounded-full text-sm font-semibold hover:bg-[#004053] transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >{{ isSubmitting ? 'Creating...' : 'Create Loan' }}</button>
            </div>

            <!-- Early exit (terminal step): Start over only -->
            <div v-else-if="isDone && isEarlyExit && !isSubmitted" class="flex justify-center">
                <button
                    type="button"
                    @click="startOver"
                    class="px-8 py-2.5 border border-gray-200 text-gray-500 rounded-full text-sm font-medium hover:bg-gray-50 transition-colors"
                >Start over</button>
            </div>

            <!-- Post-submit -->
            <div v-else-if="isSubmitted" class="text-center py-1">
                <a href="/" class="text-[#007c89] text-sm font-medium hover:underline">Go to dashboard &rarr;</a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, defineComponent, h } from 'vue'
import axios from 'axios'

// ─── SummaryRow sub-component ─────────────────────────────────────────────────
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

// ─── Helpers ──────────────────────────────────────────────────────────────────
function isValidDate(str) {
    const m = str?.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
    if (!m) return false
    const d = new Date(+m[3], +m[2] - 1, +m[1])
    return d.getFullYear() === +m[3] && d.getMonth() === +m[2] - 1 && d.getDate() === +m[1]
}

function parseDate(str) {
    const m = str?.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
    return m ? `${m[3]}-${m[2]}-${m[1]}` : null
}

function formatCurrencyRaw(val) {
    if (!val && val !== 0) return '$0'
    return '$' + Number(val).toLocaleString('en-AU', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function formatCurrency(val) {
    if (!val && val !== 0) return '—'
    return '$' + Number(val).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function renderMarkdown(text) {
    if (!text) return ''
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/\n/g, '<br>')
}

function parseDuration(str) {
    if (!str) return null
    const m = str.trim().toLowerCase().match(
        /^(\d+(?:\.\d+)?)\s*(day|days|week|weeks|fortnight|fortnights|month|months|year|years)$/
    )
    if (!m) return null
    return { value: parseFloat(m[1]), unit: m[2].replace(/s$/, '') }
}

function durationToInstalments(durationStr, frequency) {
    const d = parseDuration(durationStr)
    if (!d || !frequency) return 1
    const weeksPerUnit = { day: 1 / 7, week: 1, fortnight: 2, month: 52 / 12, year: 52 }
    const totalWeeks = d.value * (weeksPerUnit[d.unit] ?? 0)
    const weeksPerPeriod = frequency === 'Weekly' ? 1 : frequency === 'Fortnightly' ? 2 : 52 / 12
    return Math.max(1, Math.round(totalWeeks / weeksPerPeriod))
}

function calcRepaymentAmount(P, n, annualRate, frequency) {
    if (!P || !n) return 0
    if (!annualRate || annualRate === 0) return P / n
    const periodsPerYear = frequency === 'Weekly' ? 52 : frequency === 'Fortnightly' ? 26 : 12
    const r = (annualRate / 100) / periodsPerYear
    return P * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1)
}

const AU_STATES = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA']

const LOAN_TYPES = [
    'New Car', 'Car Repair', 'New House', 'House Renovation', 'Wedding',
    'New Baby', 'Household Expenses', 'New Business', 'Business Loan', 'Holiday', 'Other',
]

// ─── Conversation steps ───────────────────────────────────────────────────────
const STEPS = [
    {
        id: 'ageDisclaimer',
        question: () =>
            "Welcome to **Chipkie** — your personal loan assistant!\n\n" +
            "Before we begin: if you are **under 18**, you will need a parent or guardian " +
            "to co-sign this loan agreement.\n\nAre you ready to proceed?",
        type: 'choice',
        choices: ["I understand, let's proceed", 'Cancel'],
        field: 'ageDisclaimer',
        next: (v) => v === 'Cancel' ? 'cancelled' : null,
    },
    {
        id: 'cancelled',
        type: 'terminal',
        condition: (a) => a.ageDisclaimer === 'Cancel',
        question: () =>
            "No worries — if you change your mind, you're always welcome to come back. Take care!",
        field: null,
    },
    {
        id: 'role',
        question: () =>
            "Great! Let's set up your loan.\n\n" +
            "Are you the **lender** (giving money) or the **borrower** (receiving money)?",
        type: 'choice',
        choices: ['Lender', 'Borrower'],
        field: 'role',
    },
    {
        id: 'loanType',
        question: (a) => `You're the **${a.role.toLowerCase()}**. What is this loan for?`,
        type: 'choice',
        choices: LOAN_TYPES,
        field: 'loanType',
    },
    {
        id: 'loanName',
        question: (a) =>
            `Got it — a **${a.loanType.toLowerCase()}** loan. What would you like to call it?\n\n` +
            `*(e.g. "Sarah's car", "Holiday fund" — something you'll both recognise)*`,
        type: 'text',
        field: 'loanName',
        placeholder: "e.g. Sarah's car",
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter a name for this loan',
    },
    {
        id: 'amount',
        question: () => 'How much is the loan for?',
        type: 'currency',
        field: 'amount',
        placeholder: 'e.g. 5000',
        validate: (v) =>
            !isNaN(v) && parseFloat(v) > 0 ? null : 'Please enter a valid amount greater than 0',
        displayValue: (v) => formatCurrencyRaw(v),
    },
    {
        id: 'moneyReceived',
        question: (a) =>
            `**${formatCurrencyRaw(a.amount)}** — got it.\n\n` +
            `Has that money already been exchanged between both parties?`,
        type: 'choice',
        choices: ['Yes, already exchanged', 'Not yet'],
        field: 'moneyReceived',
    },
    {
        id: 'exchangeDate',
        condition: (a) => a.moneyReceived === 'Yes, already exchanged',
        question: () => 'When was the money exchanged? *(DD/MM/YYYY)*',
        type: 'text',
        field: 'exchangeDate',
        placeholder: 'e.g. 01/03/2026',
        validate: (v) => isValidDate(v) ? null : 'Please enter a valid date in DD/MM/YYYY format',
    },
    {
        id: 'startDate',
        question: () => 'What date should repayments start? *(DD/MM/YYYY)*',
        type: 'text',
        field: 'startDate',
        placeholder: 'e.g. 01/05/2026',
        validate: (v) => isValidDate(v) ? null : 'Please enter a valid date in DD/MM/YYYY format',
    },
    {
        id: 'frequency',
        question: () => 'How often should repayments be made?',
        type: 'choice',
        choices: ['Weekly', 'Fortnightly', 'Monthly'],
        field: 'frequency',
    },
    {
        id: 'duration',
        question: () => 'How long should the loan run?',
        type: 'choice',
        choices: ['3 months', '6 months', '12 months', '18 months', '24 months', 'Custom'],
        field: 'duration',
        onAnswer: (v, a) => {
            if (v !== 'Custom') a.instalments = String(durationToInstalments(v, a.frequency))
        },
    },
    {
        id: 'customDuration',
        condition: (a) => a.duration === 'Custom',
        question: () =>
            'Enter a custom loan duration:\n*(e.g. **"8 months"**, **"2 years"**, **"30 weeks"**)*',
        type: 'text',
        field: 'customDuration',
        placeholder: 'e.g. 8 months',
        validate: (v) =>
            parseDuration(v) !== null ? null : 'Please enter a duration like "8 months" or "2 years"',
        onAnswer: (v, a) => { a.instalments = String(durationToInstalments(v, a.frequency)) },
    },
    {
        id: 'hasInterest',
        question: (a) => {
            const n = parseInt(a.instalments)
            const freq = a.frequency.toLowerCase()
            const pmt = calcRepaymentAmount(parseFloat(a.amount), n, 0, a.frequency)
            return (
                `Interest-free, that's **${n} ${freq} repayment${n !== 1 ? 's' : ''}** ` +
                `of **${formatCurrency(pmt)}** each.\n\n` +
                `Would you like to keep it interest-free, or add interest to this loan?`
            )
        },
        type: 'choice',
        choices: ['Keep it interest-free', 'Add interest'],
        field: 'hasInterest',
        onAnswer: (v, a) => { if (v === 'Keep it interest-free') a.interestRate = '0' },
    },
    {
        id: 'interestRate',
        condition: (a) => a.hasInterest === 'Add interest',
        question: () => "What annual interest rate? *(e.g. enter **5** for 5% p.a.)*",
        type: 'number',
        field: 'interestRate',
        placeholder: 'e.g. 5',
        validate: (v) =>
            !isNaN(v) && parseFloat(v) >= 0 && parseFloat(v) <= 100
                ? null : 'Please enter a rate between 0 and 100',
        displayValue: (v) => `${v}% p.a.`,
    },
    {
        id: 'confirmInterest',
        condition: (a) => a.hasInterest === 'Add interest',
        question: (a) => {
            const n = parseInt(a.instalments)
            const P = parseFloat(a.amount)
            const rate = parseFloat(a.interestRate)
            const freq = a.frequency
            const period = freq === 'Weekly' ? 'week' : freq === 'Fortnightly' ? 'fortnight' : 'month'
            const pmtFree = calcRepaymentAmount(P, n, 0, freq)
            const pmtWith = calcRepaymentAmount(P, n, rate, freq)
            const extraPerPeriod = pmtWith - pmtFree
            const totalExtra = extraPerPeriod * n
            return (
                `At **${rate}% p.a.**, each repayment increases from ` +
                `**${formatCurrency(pmtFree)}** to **${formatCurrency(pmtWith)}** ` +
                `— **${formatCurrency(extraPerPeriod)} more** per ${period}.\n\n` +
                `Over the full term that's **${formatCurrency(totalExtra)} extra** paid in interest.\n\n` +
                `Are you happy with that?`
            )
        },
        type: 'choice',
        choices: ['Yes, confirm', 'Change rate'],
        field: 'confirmInterest',
        next: (v) => v === 'Change rate' ? 'interestRate' : null,
        onAnswer: (v, a) => {
            if (v === 'Change rate') { delete a.interestRate; delete a.confirmInterest }
        },
    },
    // ── Your details ──────────────────────────────────────────────────────────
    {
        id: 'yourFirstName',
        question: () => "Now let's get your details. What's your **first name**?",
        type: 'text',
        field: 'yourFirstName',
        placeholder: 'Your first name',
        validate: (v) => v.trim().length >= 1 ? null : 'Please enter your first name',
    },
    {
        id: 'yourLastName',
        question: (a) => `Thanks, **${a.yourFirstName}**! And your **last name**?`,
        type: 'text',
        field: 'yourLastName',
        placeholder: 'Your last name',
        validate: (v) => v.trim().length >= 1 ? null : 'Please enter your last name',
    },
    {
        id: 'yourEmail',
        question: () => "What's your **email address**?",
        type: 'email',
        field: 'yourEmail',
        placeholder: 'you@example.com',
        validate: (v) =>
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Please enter a valid email address',
    },
    {
        id: 'yourDOB',
        question: () => "What's your **date of birth**? *(DD/MM/YYYY)*",
        type: 'text',
        field: 'yourDOB',
        placeholder: 'e.g. 15/06/1990',
        validate: (v) => isValidDate(v) ? null : 'Please enter a valid date in DD/MM/YYYY format',
    },
    {
        id: 'yourCountry',
        question: () => 'What **country** are you based in?',
        type: 'text',
        field: 'yourCountry',
        placeholder: 'e.g. Australia, United Kingdom, USA',
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter your country',
    },
    {
        id: 'yourStreetAddress',
        question: (a) => {
            const isAU = /^aus/i.test(a.yourCountry || '') || /^au$/i.test(a.yourCountry || '')
            return isAU
                ? "What's your **street address**?"
                : `What's your **street address** in ${a.yourCountry}?`
        },
        type: 'text',
        field: 'yourStreetAddress',
        placeholder: 'e.g. 42 Main Street',
        validate: (v) => v.trim().length >= 3 ? null : 'Please enter your street address',
    },
    {
        id: 'yourAddress2',
        question: () => 'Any **apartment, unit or suite number**? *(optional)*',
        type: 'text',
        field: 'yourAddress2',
        placeholder: 'e.g. Unit 3',
        optional: true,
    },
    {
        id: 'yourSuburb',
        question: () => 'What **city or suburb** are you in?',
        type: 'text',
        field: 'yourSuburb',
        placeholder: 'e.g. Newtown / London',
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter your city or suburb',
    },
    {
        id: 'yourState',
        condition: (a) => /^aus/i.test(a.yourCountry || '') || /^au$/i.test(a.yourCountry || ''),
        question: () => 'Which **state or territory** are you in?',
        type: 'choice',
        choices: AU_STATES,
        field: 'yourState',
    },
    {
        id: 'yourStateText',
        condition: (a) => !/^aus/i.test(a.yourCountry || '') && !/^au$/i.test(a.yourCountry || ''),
        question: () => 'What **state, province or region** are you in?',
        type: 'text',
        field: 'yourState',
        placeholder: 'e.g. California / Ontario / England',
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter your state or region',
    },
    {
        id: 'yourPostcode',
        question: () => "What's your **postcode or ZIP code**?",
        type: 'text',
        field: 'yourPostcode',
        placeholder: 'e.g. 2042 / 10001 / SW1A 1AA',
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter your postcode',
    },
    {
        id: 'yourPhone',
        question: () => "What's your **phone number**?",
        type: 'text',
        field: 'yourPhone',
        placeholder: 'e.g. +61 412 345 678',
        validate: (v) => v.trim().length >= 6 ? null : 'Please enter your phone number',
    },
    {
        id: 'yourPassword',
        question: () =>
            "Create a **password** for your Chipkie account. *(at least 8 characters)*",
        type: 'password',
        field: 'yourPassword',
        placeholder: 'At least 8 characters',
        validate: (v) => v.length >= 8 ? null : 'Password must be at least 8 characters',
        displayValue: () => '••••••••',
    },
    {
        id: 'yourPasswordConfirm',
        question: () => "Please **confirm your password**.",
        type: 'password',
        field: 'yourPasswordConfirm',
        placeholder: 'Re-enter your password',
        validate: (v, a) => v !== a.yourPassword ? 'Passwords do not match' : null,
        displayValue: () => '••••••••',
    },
    // ── Other party details ───────────────────────────────────────────────────
    {
        id: 'otherFirstName',
        question: (a) =>
            a.role === 'Lender'
                ? "Now let's add the **borrower's** details.\n\nWhat's their **first name**?"
                : "Now let's add the **lender's** details.\n\nWhat's their **first name**?",
        type: 'text',
        field: 'otherFirstName',
        placeholder: 'Their first name',
        validate: (v) => v.trim().length >= 1 ? null : 'Please enter their first name',
    },
    {
        id: 'otherLastName',
        question: (a) => `And **${a.otherFirstName}'s** last name?`,
        type: 'text',
        field: 'otherLastName',
        placeholder: 'Their last name',
        validate: (v) => v.trim().length >= 1 ? null : 'Please enter their last name',
    },
    {
        id: 'otherEmail',
        question: (a) =>
            `What's **${a.otherFirstName}'s** email? We'll send them an invite to join Chipkie.`,
        type: 'email',
        field: 'otherEmail',
        placeholder: 'them@example.com',
        validate: (v) =>
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Please enter a valid email address',
    },
    {
        id: 'otherDOB',
        question: (a) =>
            `What's **${a.otherFirstName}'s** date of birth? *(DD/MM/YYYY — optional)*`,
        type: 'text',
        field: 'otherDOB',
        placeholder: 'e.g. 20/04/1988',
        optional: true,
        validate: (v) =>
            !v || v === '' || isValidDate(v) ? null : 'Please enter a valid date in DD/MM/YYYY format',
    },
    {
        id: 'otherState',
        question: (a) => `What **state, territory or region** does **${a.otherFirstName}** live in?`,
        type: 'text',
        field: 'otherState',
        placeholder: 'e.g. NSW / California / England',
        validate: (v) => v.trim().length >= 2 ? null : 'Please enter their state or region',
    },
    {
        id: 'otherPhone',
        question: (a) => `What's **${a.otherFirstName}'s** phone number?`,
        type: 'text',
        field: 'otherPhone',
        placeholder: 'e.g. 0487 654 321',
        validate: (v) => v.trim().length >= 6 ? null : 'Please enter their phone number',
    },
    // ── Extra signers ─────────────────────────────────────────────────────────
    {
        id: 'extraSigners',
        question: () =>
            'Do you need any **extra signers** on this loan? *(e.g. a guarantor or co-borrower)*',
        type: 'choice',
        choices: ['No extra signers', 'Yes, add a signer'],
        field: 'extraSigners',
    },
    {
        id: 'extraSignerRole',
        condition: (a) => a.extraSigners === 'Yes, add a signer',
        question: () => "What role will the extra signer have?",
        type: 'choice',
        choices: ['Lender', 'Borrower'],
        field: 'extraSignerRole',
    },
    {
        id: 'extraSignerFirstName',
        condition: (a) => a.extraSigners === 'Yes, add a signer',
        question: () => "What's the extra signer's **first name**?",
        type: 'text',
        field: 'extraSignerFirstName',
        placeholder: 'First name',
        validate: (v) => v.trim().length >= 1 ? null : 'Please enter their first name',
    },
    {
        id: 'extraSignerLastName',
        condition: (a) => a.extraSigners === 'Yes, add a signer',
        question: (a) => `And **${a.extraSignerFirstName}'s** last name?`,
        type: 'text',
        field: 'extraSignerLastName',
        placeholder: 'Last name',
        validate: (v) => v.trim().length >= 1 ? null : 'Please enter their last name',
    },
    {
        id: 'extraSignerEmail',
        condition: (a) => a.extraSigners === 'Yes, add a signer',
        question: (a) => `What's **${a.extraSignerFirstName}'s** email address?`,
        type: 'email',
        field: 'extraSignerEmail',
        placeholder: 'signer@example.com',
        validate: (v) =>
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Please enter a valid email address',
    },
    // ── Plan & add-ons ────────────────────────────────────────────────────────
    {
        id: 'plan',
        question: () =>
            "Which **Chipkie plan** would you like?\n\n" +
            "- **Free & Easy** – Free forever, core features\n" +
            "- **Premium** – $9.90/month, advanced tracking & reminders\n" +
            "- **Premium Plus** – $5.40/month, includes a legal contract",
        type: 'choice',
        choices: ['Free & Easy – Free', 'Premium – $9.90/mo', 'Premium Plus – $5.40/mo'],
        field: 'plan',
    },
    {
        id: 'contractAddOn',
        condition: (a) => a.plan !== 'Premium Plus – $5.40/mo',
        question: () =>
            "Would you like to add a **legally-binding contract** to your loan?\n*(One-time fee of $14.95)*",
        type: 'choice',
        choices: ['Add contract – $14.95', 'No thanks'],
        field: 'contractAddOn',
    },
    // ── Terms ─────────────────────────────────────────────────────────────────
    {
        id: 'termsAccept',
        question: () =>
            "Almost done! Please review Chipkie's **Terms & Conditions** and **Privacy Policy**.\n\n" +
            "By proceeding you confirm that all details are accurate and agree to Chipkie's terms of service.\n\n" +
            "*Your details will be used to create the loan agreement and notify the other party.*",
        type: 'choice',
        choices: ['I accept & create my loan'],
        field: 'termsAccept',
    },
]

// ─── State ────────────────────────────────────────────────────────────────────
const messages = ref([])
const answers = reactive({})
const inputValue = ref('')
const validationError = ref('')
const isTyping = ref(false)
const isDone = ref(false)
const isEarlyExit = ref(false)
const showSummary = ref(false)
const isSubmitting = ref(false)
const isSubmitted = ref(false)
const currentStepIndex = ref(0)

const messagesContainer = ref(null)
const inputRef = ref(null)
const pwInputRef = ref(null)
const pwValue = ref('')

// ─── Computed ─────────────────────────────────────────────────────────────────
const currentStep = computed(() => {
    if (isDone.value) return null
    return STEPS[currentStepIndex.value] ?? null
})

const repaymentAmount = computed(() => {
    const P = parseFloat(answers.amount)
    const n = parseInt(answers.instalments)
    const rate = parseFloat(answers.interestRate ?? '0') || 0
    if (!P || !n) return 0
    return calcRepaymentAmount(P, n, rate, answers.frequency)
})

const summaryData = computed(() => {
    const a = answers
    const n = parseInt(a.instalments)
    const pmt = repaymentAmount.value
    const planDisplay = a.plan === 'Free & Easy – Free' ? 'Free & Easy'
        : a.plan === 'Premium – $9.90/mo' ? 'Premium'
        : a.plan === 'Premium Plus – $5.40/mo' ? 'Premium Plus'
        : a.plan ?? '—'

    return [
        {
            title: 'Loan Details',
            rows: [
                { label: 'Loan name', value: a.loanName },
                { label: 'Loan type', value: a.loanType },
                { label: 'Amount', value: formatCurrency(a.amount) },
                { label: 'Repayments', value: n ? `${n} × ${(a.frequency || '').toLowerCase()}` : '—' },
                { label: 'Each repayment', value: pmt ? formatCurrency(pmt) : '—', highlight: true },
                { label: 'Interest', value: a.interestRate === '0' ? 'Interest-free' : a.interestRate ? `${a.interestRate}% p.a.` : '—' },
                { label: 'Money exchanged', value: a.moneyReceived === 'Yes, already exchanged' ? 'Yes' : 'Not yet' },
                ...(a.moneyReceived === 'Yes, already exchanged' ? [{ label: 'Exchange date', value: a.exchangeDate }] : []),
                { label: 'Start date', value: a.startDate },
            ],
        },
        {
            title: 'Your Details',
            rows: [
                { label: 'Name', value: [a.yourFirstName, a.yourLastName].filter(Boolean).join(' ') },
                { label: 'Email', value: a.yourEmail },
                { label: 'Date of birth', value: a.yourDOB },
                { label: 'Country', value: a.yourCountry },
                { label: 'Address', value: [a.yourStreetAddress, a.yourAddress2, a.yourSuburb, a.yourState, a.yourPostcode].filter(Boolean).join(', ') },
                { label: 'Phone', value: a.yourPhone },
            ],
        },
        {
            title: `${a.role === 'Lender' ? 'Borrower' : 'Lender'} Details`,
            rows: [
                { label: 'Name', value: [a.otherFirstName, a.otherLastName].filter(Boolean).join(' ') },
                { label: 'Email', value: a.otherEmail },
                ...(a.otherDOB ? [{ label: 'Date of birth', value: a.otherDOB }] : []),
                { label: 'State', value: a.otherState },
                { label: 'Phone', value: a.otherPhone },
            ],
        },
        {
            title: 'Plan & Add-ons',
            rows: [
                { label: 'Plan', value: planDisplay },
                { label: 'Contract', value: a.contractAddOn === 'Add contract – $14.95' ? 'Yes (+$14.95)' : 'No' },
                ...(a.extraSigners === 'Yes, add a signer' ? [{ label: 'Extra signer', value: [a.extraSignerFirstName, a.extraSignerLastName].filter(Boolean).join(' ') }] : []),
            ],
        },
    ]
})

// ─── Chat engine ──────────────────────────────────────────────────────────────
function scrollToBottom() {
    // Use setTimeout(0) instead of nextTick() — avoids Vue scheduler deadlock
    // that can occur when reactive state changes mid-flow (e.g. after password step)
    return new Promise(resolve => setTimeout(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
        resolve()
    }, 0))
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
    while (index < STEPS.length) {
        const step = STEPS[index]
        if (!step.condition || step.condition(answers)) break
        index++
    }

    if (index >= STEPS.length) {
        isDone.value = true
        showSummary.value = true
        await showBotMessage(
            `Here's your loan summary — does everything look right?\n\n` +
            `Hit **Create Loan** to send **${answers.otherFirstName ?? 'them'}** their invite!`
        )
        return
    }

    currentStepIndex.value = index
    const step = STEPS[index]
    const question = typeof step.question === 'function' ? step.question(answers) : step.question

    if (step.type === 'terminal') {
        await showBotMessage(question)
        isDone.value = true
        isEarlyExit.value = true
        return
    }

    await showBotMessage(question)
    if (step.type === 'password') {
        pwInputRef.value?.focus()
    } else if (step.type !== 'choice') {
        inputRef.value?.focus()
    }
}

function resolveNext(step, value) {
    if (!step.next) return currentStepIndex.value + 1
    const target = step.next(value)
    if (target === null || target === undefined) return currentStepIndex.value + 1
    if (typeof target === 'string') {
        const idx = STEPS.findIndex(s => s.id === target)
        return idx >= 0 ? idx : currentStepIndex.value + 1
    }
    return target
}

async function handleChoice(choice) {
    if (isTyping.value) return
    const step = currentStep.value
    if (!step) return
    isTyping.value = true
    validationError.value = ''
    try {
        if (step.field) answers[step.field] = choice
        if (step.onAnswer) step.onAnswer(choice, answers)
        messages.value.push({ from: 'user', text: choice })
        inputValue.value = ''
        await scrollToBottom()
        await advanceToStep(resolveNext(step, choice))
    } catch (e) {
        console.error('Chat error:', e)
        isTyping.value = false
        validationError.value = 'Something went wrong. Please try again.'
    }
}

async function handleTextSubmit() {
    if (isTyping.value) return
    const step = currentStep.value
    if (!step) return

    const value = String(inputValue.value ?? '').trim()

    if (step.validate) {
        const err = step.validate(value, answers)
        if (err) { validationError.value = err; return }
    } else if (!value && !step.optional) {
        validationError.value = 'Please enter a value'
        return
    }

    validationError.value = ''
    isTyping.value = true
    try {
        answers[step.field] = value
        if (step.onAnswer) step.onAnswer(value, answers)
        const display = step.displayValue ? step.displayValue(value) : value
        messages.value.push({ from: 'user', text: display })
        inputValue.value = ''
        await scrollToBottom()
        await advanceToStep(resolveNext(step, value))
    } catch (e) {
        console.error('Chat error:', e)
        isTyping.value = false
        validationError.value = 'Something went wrong. Please try again.'
    }
}

async function handlePwSubmit() {
    if (isTyping.value) return
    const step = currentStep.value
    if (!step || step.type !== 'password') return

    const value = String(pwValue.value ?? '').trim()

    if (step.validate) {
        const err = step.validate(value, answers)
        if (err) { validationError.value = err; return }
    } else if (!value) {
        validationError.value = 'Please enter a value'
        return
    }

    validationError.value = ''
    isTyping.value = true
    try {
        answers[step.field] = value
        if (step.onAnswer) step.onAnswer(value, answers)
        const display = step.displayValue ? step.displayValue(value) : value
        messages.value.push({ from: 'user', text: display })
        pwValue.value = ''
        await scrollToBottom()
        await advanceToStep(resolveNext(step, value))
    } catch (e) {
        console.error('Chat error:', e)
        isTyping.value = false
        validationError.value = 'Something went wrong. Please try again.'
    }
}

async function handleSkip() {
    if (isTyping.value) return
    const step = currentStep.value
    if (!step || !step.optional) return
    validationError.value = ''
    answers[step.field] = ''
    messages.value.push({ from: 'user', text: '(skipped)' })
    inputValue.value = ''
    await scrollToBottom()
    await advanceToStep(resolveNext(step, ''))
}

async function submitLoan() {
    isSubmitting.value = true
    validationError.value = ''
    try {
        const a = answers
        const planKey = a.plan === 'Free & Easy – Free' ? 'Free'
            : a.plan === 'Premium – $9.90/mo' ? 'Premium'
            : 'Premium Plus'

        await axios.post('/loans/chat', {
            role:                a.role,
            loan_type:           a.loanType,
            loan_name:           a.loanName,
            amount:              a.amount,
            frequency:           a.frequency,
            instalments:         a.instalments,
            interest_rate:       a.interestRate,
            money_received:      a.moneyReceived === 'Yes, already exchanged',
            exchange_date:       parseDate(a.exchangeDate) ?? null,
            start_date:          parseDate(a.startDate),
            your_first_name:     a.yourFirstName,
            your_last_name:      a.yourLastName,
            your_email:          a.yourEmail,
            your_dob:            parseDate(a.yourDOB),
            your_country:        a.yourCountry,
            your_street_address: a.yourStreetAddress,
            your_address_2:      a.yourAddress2 || null,
            your_suburb:         a.yourSuburb,
            your_state:          a.yourState,
            your_postcode:       a.yourPostcode,
            your_phone:          a.yourPhone,
            your_password:       a.yourPassword,
            other_first_name:    a.otherFirstName,
            other_last_name:     a.otherLastName,
            other_email:         a.otherEmail,
            other_dob:           parseDate(a.otherDOB) || null,
            other_state:         a.otherState,
            other_phone:         a.otherPhone,
            extra_signers:       a.extraSigners === 'Yes, add a signer',
            plan:                planKey,
            contract_add_on:     a.contractAddOn === 'Add contract – $14.95',
            terms_accepted:      true,
        })
        isSubmitted.value = true
        await showBotMessage(
            `Your loan has been created!\n\n` +
            `We've sent **${a.otherFirstName}** an invite at **${a.otherEmail}**. ` +
            `Once they join Chipkie, your loan will be fully active.`,
            500
        )
    } catch (err) {
        const data = err.response?.data
        if (data?.errors) {
            const first = Object.values(data.errors)[0]
            validationError.value = Array.isArray(first) ? first[0] : first
        } else {
            validationError.value = data?.message || 'Something went wrong. Please try again.'
        }
    } finally {
        isSubmitting.value = false
    }
}

function startOver() {
    messages.value = []
    Object.keys(answers).forEach(k => delete answers[k])
    inputValue.value = ''
    pwValue.value = ''
    validationError.value = ''
    isDone.value = false
    isEarlyExit.value = false
    showSummary.value = false
    isSubmitted.value = false
    currentStepIndex.value = 0
    advanceToStep(0)
}

onMounted(() => { advanceToStep(0) })
</script>

<style scoped>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
.message-enter-active { transition: all 0.3s ease-out; }
.message-enter-from { opacity: 0; transform: translateY(8px); }
</style>
