<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cashier's version of this migration tries to add stripe_id etc. to users,
// but our create_users_table already includes those columns. This no-op
// migration shadows the Cashier package migration so it records as done.
return new class extends Migration
{
    public function up(): void
    {
        // Stripe/Cashier columns are already defined in create_users_table
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
