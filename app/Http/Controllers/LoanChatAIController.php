<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class LoanChatAIController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('LoanChat');
    }

    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'messages'          => ['required', 'array', 'min:1'],
            'messages.*.role'   => ['required', 'in:user,assistant'],
            'messages.*.content'=> ['required', 'string'],
        ]);

        try {
            $result = $this->callClaude($request->input('messages'));
        } catch (\Exception $e) {
            return response()->json(['error' => 'AI service unavailable. Please try again.'], 503);
        }

        // Claude decided to create the loan
        if (($result['stop_reason'] ?? '') === 'tool_use') {
            $toolBlock = collect($result['content'])->firstWhere('type', 'tool_use');
            $textBlock = collect($result['content'])->firstWhere('type', 'text');

            if ($toolBlock && $toolBlock['name'] === 'create_loan') {
                return response()->json([
                    'message'        => $textBlock['text'] ?? 'All set! Please create a password below to complete your account.',
                    'loan_ready'     => true,
                    'collected_data' => $toolBlock['input'],
                ]);
            }
        }

        $textBlock = collect($result['content'])->firstWhere('type', 'text');
        return response()->json(['message' => $textBlock['text'] ?? '']);
    }

    private function callClaude(array $messages): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model'       => 'claude-sonnet-4-6',
            'max_tokens'  => 1024,
            'system'      => $this->systemPrompt(),
            'tools'       => [$this->createLoanTool()],
            'tool_choice' => ['type' => 'auto'],
            'messages'    => $messages,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Claude API error: ' . $response->status());
        }

        return $response->json();
    }

    private function systemPrompt(): string
    {
        $today = now()->format('d/m/Y');

        return <<<PROMPT
You are Chipkie's friendly loan assistant. Chipkie helps people create personal loan agreements between friends and family.

Today's date is {$today}.

Your task: guide the user through setting up a loan agreement via natural conversation. Collect the information below. Be warm, concise, and use **bold** for key numbers and terms.

━━━ LOAN DETAILS ━━━
• Role — lender (giving money) or borrower (receiving money)?
• Loan type — what is it for? (car, renovation, wedding, holiday, business, personal, etc.)
• Loan name — a friendly name both parties will recognise (e.g. "Sarah's Car Loan")
• Amount — must be greater than $0
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
• Plan: Free (free forever, core features) | Premium ($9.90/mo, tracking & reminders) | Premium Plus ($5.40/mo, includes legal contract)
• Contract add-on: only ask if they didn't choose Premium Plus — legal contract for $14.95 one-off?
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

    private function createLoanTool(): array
    {
        return [
            'name'        => 'create_loan',
            'description' => 'Create the loan agreement. Call ONLY after presenting a full summary and receiving explicit user confirmation.',
            'input_schema' => [
                'type'       => 'object',
                'required'   => [
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
}
