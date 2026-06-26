<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_sync_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('mode')->default('preview_only');
            $table->unsignedInteger('max_products_per_batch')->default(25);
            $table->unsignedInteger('max_products_per_run')->default(100);
            $table->unsignedInteger('woo_query_limit')->default(100);
            $table->unsignedInteger('front_write_limit')->default(25);
            $table->boolean('sync_only_opted_in_products')->default(true);
            $table->boolean('include_simple_products')->default(true);
            $table->boolean('include_variable_products')->default(false);
            $table->boolean('include_variations')->default(false);
            $table->boolean('require_sku')->default(true);
            $table->boolean('require_gtin')->default(true);
            $table->boolean('require_price')->default(true);
            $table->boolean('require_brand')->default(false);
            $table->boolean('require_category')->default(false);
            $table->string('default_front_group_strategy')->nullable();
            $table->string('default_front_subgroup_strategy')->nullable();
            $table->string('default_front_brand_strategy')->nullable();
            $table->string('price_strategy')->default('regular_price_only');
            $table->string('stock_strategy')->default('do_not_sync_stock_yet');
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'mode']);
        });

        Schema::create('product_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_sync_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('mode');
            $table->unsignedInteger('total_candidates')->default(0);
            $table->unsignedInteger('total_ready')->default(0);
            $table->unsignedInteger('total_blocked')->default(0);
            $table->unsignedInteger('total_synced')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedInteger('total_skipped')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary_json')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['product_sync_profile_id', 'created_at']);
        });

        Schema::create('product_sync_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_sync_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woo_product_id');
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->string('woo_item_key');
            $table->string('woo_name')->nullable();
            $table->string('woo_sku')->nullable();
            $table->string('detected_gtin')->nullable();
            $table->string('detected_gtin_key')->nullable();
            $table->string('front_match_status')->nullable();
            $table->string('proposed_front_product_ext_id')->nullable();
            $table->string('proposed_front_identity')->nullable();
            $table->string('proposed_front_external_sku')->nullable();
            $table->json('proposed_front_payload_json')->nullable();
            $table->string('validation_status');
            $table->string('sync_status')->default('not_started');
            $table->json('validation_errors_json')->nullable();
            $table->json('validation_warnings_json')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'woo_item_key']);
            $table->index(['product_sync_run_id', 'sync_status']);
            $table->index(['product_sync_run_id', 'validation_status']);
            $table->index(['organization_id', 'detected_gtin']);
            $table->index(['organization_id', 'woo_sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sync_run_items');
        Schema::dropIfExists('product_sync_runs');
        Schema::dropIfExists('product_sync_profiles');
    }
};
