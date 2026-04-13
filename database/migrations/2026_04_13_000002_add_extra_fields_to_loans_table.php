<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('loan_name')->nullable()->after('id');
            $table->string('loan_type', 50)->nullable()->after('loan_name');
            $table->boolean('money_received')->default(false)->after('interest_rate');
            $table->date('exchange_date')->nullable()->after('money_received');
            $table->date('start_date')->nullable()->after('exchange_date');
            $table->boolean('extra_signers')->default(false)->after('start_date');
            $table->boolean('contract_add_on')->default(false)->after('extra_signers');
        });

        // Expand plan enum to include 'free'
        DB::statement("ALTER TABLE loans MODIFY COLUMN plan ENUM('free','premium','premiumplus') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'loan_name', 'loan_type', 'money_received',
                'exchange_date', 'start_date', 'extra_signers', 'contract_add_on',
            ]);
        });

        DB::statement("ALTER TABLE loans MODIFY COLUMN plan ENUM('premium','premiumplus') NULL DEFAULT NULL");
    }
};
