<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoanChatController extends Controller
{
    /**
     * Render the chat-based loan creation page.
     */
    public function show(): Response
    {
        return Inertia::render('LoanChat');
    }

    /**
     * Create a loan from the chat conversation answers.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role'                => ['required', 'in:Lender,Borrower'],
            'loan_type'           => ['required', 'string', 'max:50'],
            'loan_name'           => ['required', 'string', 'min:2', 'max:255'],
            'amount'              => ['required', 'numeric', 'min:1'],
            'frequency'           => ['required', 'in:Weekly,Fortnightly,Monthly'],
            'instalments'         => ['required', 'integer', 'min:1', 'max:600'],
            'interest_rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'money_received'      => ['required', 'boolean'],
            'exchange_date'       => ['nullable', 'date'],
            'start_date'          => ['required', 'date'],
            'your_first_name'     => ['required', 'string', 'min:1', 'max:100'],
            'your_last_name'      => ['required', 'string', 'min:1', 'max:100'],
            'your_email'          => ['required', 'email', 'max:255'],
            'your_dob'            => ['required', 'date'],
            'your_street_address' => ['required', 'string', 'max:255'],
            'your_address_2'      => ['nullable', 'string', 'max:255'],
            'your_suburb'         => ['required', 'string', 'max:100'],
            'your_state'          => ['required', 'string', 'max:10'],
            'your_postcode'       => ['required', 'string', 'max:10'],
            'your_phone'          => ['required', 'string', 'max:30'],
            'your_password'       => ['required', 'string', 'min:8'],
            'other_first_name'    => ['required', 'string', 'min:1', 'max:100'],
            'other_last_name'     => ['required', 'string', 'min:1', 'max:100'],
            'other_email'         => ['required', 'email', 'max:255'],
            'other_dob'           => ['nullable', 'date'],
            'other_state'         => ['required', 'string', 'max:10'],
            'other_phone'         => ['required', 'string', 'max:30'],
            'extra_signers'       => ['required', 'boolean'],
            'plan'                => ['required', 'in:Free,Premium,Premium Plus'],
            'contract_add_on'     => ['required', 'boolean'],
            'terms_accepted'      => ['required', 'accepted'],
        ]);

        $loan = DB::transaction(function () use ($data) {
            // ── Resolve or create the initiating user ────────────────────────
            $initiator = User::firstOrNew(['email' => $data['your_email']]);
            $isNewInitiator = ! $initiator->exists;

            $initiator->fill([
                'name'           => $data['your_first_name'] . ' ' . $data['your_last_name'],
                'first_name'     => $data['your_first_name'],
                'last_name'      => $data['your_last_name'],
                'date_of_birth'  => $data['your_dob'],
                'phone'          => $data['your_phone'],
                'street_address' => $data['your_street_address'],
                'address_line_2' => $data['your_address_2'] ?? null,
                'suburb'         => $data['your_suburb'],
                'state'          => $data['your_state'],
                'postcode'       => $data['your_postcode'],
            ]);

            if ($isNewInitiator) {
                $initiator->password = bcrypt($data['your_password']);
            }

            $initiator->save();

            // ── Resolve or create the invited party ──────────────────────────
            $invitee = User::firstOrNew(['email' => $data['other_email']]);

            $invitee->fill([
                'name'          => $data['other_first_name'] . ' ' . $data['other_last_name'],
                'first_name'    => $data['other_first_name'],
                'last_name'     => $data['other_last_name'],
                'date_of_birth' => $data['other_dob'] ?? null,
                'phone'         => $data['other_phone'],
                'state'         => $data['other_state'],
            ]);

            if (! $invitee->exists) {
                $invitee->password = bcrypt(Str::random(24));
            }

            $invitee->save();

            // ── Map plan label to DB enum value ──────────────────────────────
            $planMap = [
                'Free'         => 'free',
                'Premium'      => 'premium',
                'Premium Plus' => 'premiumplus',
            ];

            // ── Create loan record ───────────────────────────────────────────
            $loan = Loan::create([
                'loan_name'     => $data['loan_name'],
                'loan_type'     => $data['loan_type'],
                'amount'        => $data['amount'],
                'frequency'     => $data['frequency'],
                'instalments'   => (int) $data['instalments'],
                'interest_rate' => $data['interest_rate'],
                'money_received'  => $data['money_received'],
                'exchange_date'   => $data['exchange_date'] ?? null,
                'start_date'      => $data['start_date'],
                'extra_signers'   => $data['extra_signers'],
                'contract_add_on' => $data['contract_add_on'],
                'plan'            => $planMap[$data['plan']] ?? null,
                'status'          => 'active',
            ]);

            // ── Link lender and borrower ─────────────────────────────────────
            $lenderId  = $data['role'] === 'Lender' ? $initiator->id : $invitee->id;
            $borrowerId = $data['role'] === 'Borrower' ? $initiator->id : $invitee->id;

            LoanUser::create(['loan_id' => $loan->id, 'user_id' => $lenderId,  'role' => 'lender']);
            LoanUser::create(['loan_id' => $loan->id, 'user_id' => $borrowerId, 'role' => 'borrower']);

            // ── Generate instalment schedule ─────────────────────────────────
            $this->createInstalments($loan, $data);

            // ── Generate invite token for the other party ────────────────────
            DB::table('loan_tokens')->insert([
                'loan_id'    => $loan->id,
                'user_id'    => $invitee->id,
                'token'      => Str::random(40),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $loan;
        });

        return response()->json([
            'message' => 'Loan created successfully.',
            'loan_id' => $loan->id,
        ], 201);
    }

    /**
     * Generate the instalment schedule for a loan.
     */
    private function createInstalments(Loan $loan, array $data): void
    {
        $principal  = (float) $data['amount'];
        $n          = (int) $data['instalments'];
        $annualRate = (float) $data['interest_rate'];
        $frequency  = $data['frequency'];

        $periodsPerYear = match ($frequency) {
            'Weekly'      => 52,
            'Fortnightly' => 26,
            default       => 12,
        };

        $daysPerPeriod = match ($frequency) {
            'Weekly'      => 7,
            'Fortnightly' => 14,
            default       => null,
        };

        if ($annualRate > 0) {
            $r       = ($annualRate / 100) / $periodsPerYear;
            $payment = round($principal * $r * pow(1 + $r, $n) / (pow(1 + $r, $n) - 1), 2);
        } else {
            $payment = round($principal / $n, 2);
        }

        // Use start_date as the anchor for the first due date
        $dueDate = isset($data['start_date'])
            ? \Carbon\Carbon::parse($data['start_date'])
            : now();

        $rows = [];

        for ($i = 0; $i < $n; $i++) {
            if ($i > 0) {
                $dueDate = $daysPerPeriod
                    ? $dueDate->copy()->addDays($daysPerPeriod)
                    : $dueDate->copy()->addMonth();
            }

            $rows[] = [
                'loan_id'    => $loan->id,
                'amount'     => $payment,
                'due_date'   => $dueDate->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('instalments')->insert($rows);
    }
}
