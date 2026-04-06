<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['lender', 'borrower']);
            $table->timestamps();

            $table->unique(['loan_id', 'role']); // one lender and one borrower per loan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_users');
    }
};
