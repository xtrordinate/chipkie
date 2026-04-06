<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 12, 2);
            $table->enum('frequency', ['Weekly', 'Fortnightly', 'Monthly'])->default('Monthly');
            $table->unsignedInteger('instalments');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->string('stripe_id')->nullable()->index();
            $table->enum('status', ['active', 'hold', 'rejected'])->default('active');
            $table->enum('plan', ['premium', 'premiumplus'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
