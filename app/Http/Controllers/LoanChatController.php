<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            'role'          => ['required', 'in:Lender,Borrower'],
            'amount'        => ['required', 'numeric', 'min:1'],
            'your_name'     => ['required', 'string', 'min:2', 'max:255'],
            'your_email'    => ['required', 'email', 'max:255'],
            'other_name'    => ['required', 'string', 'min:2', 'max:255'],
            'other_email'   => ['required', 'email', 'max:255'],
            'frequency'     => ['required', 'in:Weekly,Fortnightly,Monthly'],
            'instalments'   => ['required', 'integer', 'min:1', 'max:600'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $loan = DB::transaction(function () use ($data) {
            // Resolve or create the initiating user.
            $initiator = User::firstOrCreate(
                ['email' => $data['your_email']],
                [
                    'name'     => $data['your_name'],
                    'password' => bcrypt(Str::random(24)),
                ]
            );

            // Resolve or create the invited user.
            $invitee = User::firstOrCreate(
                ['email' => $data['other_email']],
                [
                    'name'     => $data['other_name'],
                    'password' => bcrypt(Str::random(24)),
                ]
            );

            // Determine lender / borrower IDs.
            $lenderId   = $data['role'] === 'Lender' ? $initiator->id : $invitee->id;
            $borrowerId  = $data['role'] === 'Borrower' ? $initiator->id : $invitee->id;

            // Create the loan record.
            $loan = Loan::create([
                'amount'        => $data['amount'],
                'frequency'     => $data['frequency'],
                'instalments'   => (int) $data['instalments'],
                'interest_rate' => $data['interest_rate'],
                'status'        => 'active',
            ]);

            // Create stakeholder records.
            LoanUser::create(['loan_id' => $loan->id, 'user_id' => $lenderId,  'role' => 'lender']);
            LoanUser::create(['loan_id' => $loan->id, 'user_id' => $borrowerId, 'role' => 'borrower']);

            // Generate instalment schedule.
            $this->createInstalments($loan, $data);

            // Generate an invite token for the other party.
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
        $principal    = (float) $data['amount'];
        $n            = (int)   $data['instalments'];
        $annualRate   = (float) $data['interest_rate'];
        $frequency    = $data['frequency'];

        $periodsPerYear = match ($frequency) {
            'Weekly'      => 52,
            'Fortnightly' => 26,
            default       => 12,
        };

        $daysPerPeriod = match ($frequency) {
            'Weekly'      => 7,
            'Fortnightly' => 14,
            default       => null, // use addMonth()
        };

        // Calculate per-period repayment amount.
        if ($annualRate > 0) {
            $r       = ($annualRate / 100) / $periodsPerYear;
            $payment = round($principal * $r * pow(1 + $r, $n) / (pow(1 + $r, $n) - 1), 2);
        } else {
            $payment = round($principal / $n, 2);
        }

        $dueDate = now();
        $rows    = [];

        for ($i = 0; $i < $n; $i++) {
            $dueDate = $daysPerPeriod
                ? $dueDate->copy()->addDays($daysPerPeriod)
                : $dueDate->copy()->addMonth();

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
