<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->string('phone', 30)->nullable()->after('date_of_birth');
            $table->string('street_address')->nullable()->after('phone');
            $table->string('address_line_2')->nullable()->after('street_address');
            $table->string('suburb', 100)->nullable()->after('address_line_2');
            $table->string('state', 10)->nullable()->after('suburb');
            $table->string('postcode', 10)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'date_of_birth', 'phone',
                'street_address', 'address_line_2', 'suburb', 'state', 'postcode',
            ]);
        });
    }
};
