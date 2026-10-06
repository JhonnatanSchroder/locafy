<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('contract_id')->constrained();
            $t->decimal('rental_amount', 14, 2);
            $t->decimal('freight_amount', 14, 2);
            $t->decimal('total_amount', 14, 2);
            $t->date('due_date');
            $t->date('calculated_until');
            $t->string('status')->default('PENDING');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status', 'due_date']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('charge_id')->constrained();
            $t->decimal('amount', 14, 2);
            $t->dateTime('paid_at');
            $t->string('method');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('charges');
    }
};
