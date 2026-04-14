<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('phone');
        });

        // Also expand state and postcode columns for international values
        Schema::table('users', function (Blueprint $table) {
            $table->string('state', 100)->nullable()->change();
            $table->string('postcode', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country');
            $table->string('state', 10)->nullable()->change();
            $table->string('postcode', 10)->nullable()->change();
        });
    }
};
