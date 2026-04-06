<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_id',
        'status',
        'plan',
        'amount',
        'frequency',
        'instalments',
        'interest_rate',
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
