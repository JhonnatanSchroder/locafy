<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', fn (Blueprint $table) => $table->unsignedSmallInteger('charge_interval_days')->default(15));
        Schema::table('charges', function (Blueprint $table) {
            $table->date('cycle_due_date')->nullable();
            $table->unique(['contract_id', 'cycle_due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropUnique(['contract_id', 'cycle_due_date']);
            $table->dropColumn('cycle_due_date');
        });
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn('charge_interval_days'));
    }
};
