<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_sync_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('woo_page_size')->default(50)->after('max_products_per_run');
            $table->unsignedInteger('front_page_size')->default(50)->after('woo_page_size');
            $table->unsignedInteger('max_runtime_seconds')->nullable()->after('front_page_size');
            $table->unsignedInteger('rate_limit_per_minute')->nullable()->after('max_runtime_seconds');
            $table->string('sync_scope')->default('selected_only')->after('rate_limit_per_minute');
            $table->boolean('include_draft_products')->default(false)->after('include_variations');
            $table->boolean('include_private_products')->default(false)->after('include_draft_products');
            $table->boolean('include_out_of_stock_products')->default(true)->after('include_private_products');
            $table->boolean('exclude_discontinued_products')->default(true)->after('include_out_of_stock_products');
            $table->string('product_identity_strategy')->default('woo_id_as_front_extid')->after('require_category');
            $table->string('gtin_field_strategy')->default('auto_detect')->after('product_identity_strategy');
            $table->string('configured_gtin_meta_key')->nullable()->after('gtin_field_strategy');
            $table->string('category_mapping_strategy')->nullable()->after('configured_gtin_meta_key');
            $table->string('brand_mapping_strategy')->nullable()->after('category_mapping_strategy');
            $table->boolean('incremental_sync_enabled')->default(false)->after('stock_strategy');
            $table->boolean('webhook_updates_enabled')->default(false)->after('incremental_sync_enabled');
            $table->boolean('reconciliation_enabled')->default(false)->after('webhook_updates_enabled');
            $table->index(['organization_id', 'sync_scope']);
        });

        Schema::table('product_sync_runs', function (Blueprint $table): void {
            $table->string('run_type')->default('preview')->after('created_by_user_id');
            $table->string('scope')->nullable()->after('mode');
            $table->json('cursor_json')->nullable()->after('scope');
            $table->json('checkpoint_json')->nullable()->after('cursor_json');
            $table->unsignedInteger('total_pending')->default(0)->after('total_skipped');
            $table->unsignedInteger('total_variations')->default(0)->after('total_pending');
            $table->timestamp('paused_at')->nullable()->after('started_at');
            $table->index(['organization_id', 'run_type', 'created_at']);
        });

        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('woo_parent_product_id')->nullable()->after('woo_item_key');
            $table->string('woo_type')->nullable()->after('woo_name');
            $table->string('front_product_id')->nullable()->after('front_match_status');
            $table->string('front_product_ext_id')->nullable()->after('front_product_id');
            $table->string('front_identity')->nullable()->after('front_product_ext_id');
            $table->string('front_external_sku')->nullable()->after('front_identity');
            $table->string('payload_hash')->nullable()->after('proposed_front_payload_json');
            $table->unsignedInteger('attempt_count')->default(0)->after('last_error');
            $table->index(['organization_id', 'front_product_ext_id']);
            $table->index(['product_sync_run_id', 'woo_product_id']);
            $table->index(['product_sync_run_id', 'woo_variation_id']);
        });

        Schema::create('product_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_system')->default('woocommerce');
            $table->string('event_type');
            $table->unsignedBigInteger('woo_product_id')->nullable();
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->string('woo_item_key')->nullable();
            $table->string('dedupe_key');
            $table->string('status')->default('pending');
            $table->integer('priority')->default(0);
            $table->json('payload_summary_json')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'dedupe_key']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'woo_item_key']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sync_events');

        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'front_product_ext_id']);
            $table->dropIndex(['product_sync_run_id', 'woo_product_id']);
            $table->dropIndex(['product_sync_run_id', 'woo_variation_id']);
            $table->dropColumn([
                'woo_parent_product_id',
                'woo_type',
                'front_product_id',
                'front_product_ext_id',
                'front_identity',
                'front_external_sku',
                'payload_hash',
                'attempt_count',
            ]);
        });

        Schema::table('product_sync_runs', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'run_type', 'created_at']);
            $table->dropColumn([
                'run_type',
                'scope',
                'cursor_json',
                'checkpoint_json',
                'total_pending',
                'total_variations',
                'paused_at',
            ]);
        });

        Schema::table('product_sync_profiles', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'sync_scope']);
            $table->dropColumn([
                'woo_page_size',
                'front_page_size',
                'max_runtime_seconds',
                'rate_limit_per_minute',
                'sync_scope',
                'include_draft_products',
                'include_private_products',
                'include_out_of_stock_products',
                'exclude_discontinued_products',
                'product_identity_strategy',
                'gtin_field_strategy',
                'configured_gtin_meta_key',
                'category_mapping_strategy',
                'brand_mapping_strategy',
                'incremental_sync_enabled',
                'webhook_updates_enabled',
                'reconciliation_enabled',
            ]);
        });
    }
};
