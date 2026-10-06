<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('role');
            $table->timestamp('must_change_password_at')->nullable()->after('remember_token');
            $table->foreignId('created_by_user_id')->nullable()->after('must_change_password_at')->constrained('users')->nullOnDelete();
            $table->foreignId('role_updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('deactivated_by_user_id')->nullable()->after('role_updated_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable()->after('deactivated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['role_updated_by_user_id']);
            $table->dropForeign(['deactivated_by_user_id']);
            $table->dropColumn([
                'active',
                'must_change_password_at',
                'created_by_user_id',
                'role_updated_by_user_id',
                'deactivated_by_user_id',
                'deactivated_at',
            ]);
        });
    }
};
