<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('front_sale_imports', function (Blueprint $table): void {
            $table->string('transaction_type')->default('sale')->after('handling_mode');
            $table->index(['organization_id', 'transaction_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('front_sale_imports', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'transaction_type', 'created_at']);
            $table->dropColumn('transaction_type');
        });
    }
};
