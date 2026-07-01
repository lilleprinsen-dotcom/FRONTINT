<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_sync_profiles', function (Blueprint $table): void {
            $table->string('sale_price_list_name')->default('WooCommerce Sale Prices')->after('price_strategy');
        });

        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->string('sale_price_sync_status')->default('not_applicable')->after('sync_status');
            $table->text('sale_price_last_error')->nullable()->after('last_error');
            $table->json('sale_price_last_request_summary_json')->nullable()->after('last_response_summary_json');
            $table->json('sale_price_last_response_summary_json')->nullable()->after('sale_price_last_request_summary_json');
            $table->unsignedInteger('sale_price_attempt_count')->default(0)->after('attempt_count');
            $table->timestamp('sale_price_last_attempted_at')->nullable()->after('last_attempted_at');
            $table->timestamp('sale_price_synced_at')->nullable()->after('synced_at');

            $table->index(['product_sync_run_id', 'sale_price_sync_status']);
        });
    }

    public function down(): void
    {
        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->dropIndex(['product_sync_run_id', 'sale_price_sync_status']);
            $table->dropColumn([
                'sale_price_sync_status',
                'sale_price_last_error',
                'sale_price_last_request_summary_json',
                'sale_price_last_response_summary_json',
                'sale_price_attempt_count',
                'sale_price_last_attempted_at',
                'sale_price_synced_at',
            ]);
        });

        Schema::table('product_sync_profiles', function (Blueprint $table): void {
            $table->dropColumn('sale_price_list_name');
        });
    }
};
