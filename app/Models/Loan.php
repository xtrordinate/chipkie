<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_name',
        'loan_type',
        'stripe_id',
        'status',
        'plan',
        'amount',
        'frequency',
        'instalments',
        'interest_rate',
        'money_received',
        'exchange_date',
        'start_date',
        'extra_signers',
        'contract_add_on',
    ];

    public function stakeholders(): HasMany
    {
        return $this->hasMany(LoanUser::class);
    }

    public function instalmentSchedule(): HasMany
    {
        return $this->hasMany(Instalment::class);
    }
}
