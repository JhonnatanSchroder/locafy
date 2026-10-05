<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('contract_id');
            $table->renameColumn('amount', 'unit_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freights', function (Blueprint $table) {
            $table->renameColumn('unit_amount', 'amount');
            $table->dropColumn('quantity');
        });
    }
};
