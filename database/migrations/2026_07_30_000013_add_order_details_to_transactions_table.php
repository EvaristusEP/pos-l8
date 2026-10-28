<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('cashier_id');
            $table->string('service_type', 20)->nullable()->after('customer_name');
            $table->string('pager_number', 30)->nullable()->after('service_type');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'service_type', 'pager_number']);
        });
    }
};
