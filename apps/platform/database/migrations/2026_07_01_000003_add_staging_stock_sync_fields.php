<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_sync_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('front_stock_id')->nullable()->after('stock_strategy');
            $table->string('front_stock_ext_id')->nullable()->after('front_stock_id');
        });

        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->integer('woo_stock_quantity')->nullable()->after('woo_sku');
            $table->string('stock_sync_status')->default('not_applicable')->after('sale_price_sync_status');
            $table->text('stock_last_error')->nullable()->after('sale_price_last_error');
            $table->json('stock_last_request_summary_json')->nullable()->after('sale_price_last_response_summary_json');
            $table->json('stock_last_response_summary_json')->nullable()->after('stock_last_request_summary_json');
            $table->unsignedInteger('stock_attempt_count')->default(0)->after('sale_price_attempt_count');
            $table->timestamp('stock_last_attempted_at')->nullable()->after('sale_price_last_attempted_at');
            $table->timestamp('stock_synced_at')->nullable()->after('sale_price_synced_at');

            $table->index(['product_sync_run_id', 'stock_sync_status']);
        });
    }

    public function down(): void
    {
        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->dropIndex(['product_sync_run_id', 'stock_sync_status']);
            $table->dropColumn([
                'woo_stock_quantity',
                'stock_sync_status',
                'stock_last_error',
                'stock_last_request_summary_json',
                'stock_last_response_summary_json',
                'stock_attempt_count',
                'stock_last_attempted_at',
                'stock_synced_at',
            ]);
        });

        Schema::table('product_sync_profiles', function (Blueprint $table): void {
            $table->dropColumn(['front_stock_id', 'front_stock_ext_id']);
        });
    }
};
