<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('is_admin');
            $table->string('company_name')->nullable()->after('phone');
            $table->string('website')->nullable()->after('company_name');
            $table->decimal('commission_rate', 5, 2)->default(0)->after('credits');
            $table->integer('credit_limit')->default(0)->after('commission_rate');
            $table->json('permissions')->nullable()->after('max_connections');
            $table->string('mac_address')->nullable()->after('last_ip_address');
            $table->string('country')->nullable()->after('mac_address');
            $table->boolean('allow_sub_resellers')->default(false)->after('is_reseller');
            $table->boolean('white_label')->default(false)->after('allow_sub_resellers');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'company_name', 'website', 'commission_rate',
                'credit_limit', 'permissions', 'mac_address', 'country',
                'allow_sub_resellers', 'white_label',
            ]);
        });
    }
};
