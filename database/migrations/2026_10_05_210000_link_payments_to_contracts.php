<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->constrained();
            $table->foreignId('charge_id')->nullable()->change();
            $table->index(['company_id', 'contract_id']);
        });
        DB::table('payments')->update(['contract_id' => DB::raw('(select contract_id from charges where charges.id = payments.charge_id and charges.company_id = payments.company_id)')]);
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNull('charge_id')->exists()) {
            throw new RuntimeException('Cannot roll back direct contract payments without losing financial history.');
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'contract_id']);
            $table->dropForeign(['contract_id']);
            $table->dropColumn('contract_id');
            $table->foreignId('charge_id')->nullable(false)->change();
        });
    }
};
