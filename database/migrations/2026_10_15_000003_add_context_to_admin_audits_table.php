<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audits', function (Blueprint $table): void {
            $table->string('source')->nullable()->after('user_id');
            $table->string('module')->nullable()->after('source');
            $table->json('user_snapshot')->nullable()->after('module');
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_audits', function (Blueprint $table): void {
            $table->dropIndex('admin_audits_company_id_created_at_index');
            $table->dropIndex('admin_audits_company_id_user_id_index');
            $table->dropIndex('admin_audits_company_id_module_index');
            $table->dropColumn(['source', 'module', 'user_snapshot']);
        });
    }
};
