<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('woo_item_key');
            $table->unsignedBigInteger('woo_product_id');
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->string('front_product_id')->nullable();
            $table->string('front_product_ext_id')->nullable();
            $table->string('front_identity')->nullable();
            $table->string('sku')->nullable();
            $table->string('gtin')->nullable();
            $table->string('external_sku')->nullable();
            $table->string('front_stock_id')->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'woo_item_key']);
            $table->index(['organization_id', 'front_product_ext_id']);
            $table->index(['organization_id', 'sku']);
            $table->index(['organization_id', 'gtin']);
            $table->index(['organization_id', 'external_sku']);
        });

        Schema::create('customer_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woo_customer_id')->nullable();
            $table->string('front_customer_id')->nullable();
            $table->string('email_hash')->nullable();
            $table->string('phone_hash')->nullable();
            $table->unsignedTinyInteger('match_confidence')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'woo_customer_id']);
            $table->unique(['organization_id', 'front_customer_id']);
            $table->index(['organization_id', 'email_hash']);
            $table->index(['organization_id', 'phone_hash']);
        });

        Schema::create('order_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woo_order_id')->nullable();
            $table->string('front_order_id')->nullable();
            $table->string('front_receipt_id')->nullable();
            $table->string('source');
            $table->string('status')->default('pending');
            $table->string('idempotency_key');
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'woo_order_id']);
            $table->index(['organization_id', 'front_order_id']);
        });

        Schema::create('stock_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('source_system');
            $table->string('movement_type');
            $table->integer('quantity_delta');
            $table->integer('physical_quantity_after')->nullable();
            $table->integer('reserved_quantity_after')->nullable();
            $table->integer('available_quantity_after')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('idempotency_key');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'product_mapping_id', 'created_at']);
        });

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_mapping_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woo_order_id')->nullable();
            $table->string('front_reservation_id')->nullable();
            $table->integer('quantity');
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('idempotency_key');
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'expires_at']);
        });

        Schema::create('product_validation_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woo_product_id');
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->string('issue_type');
            $table->string('severity')->default('warning');
            $table->text('message');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'issue_type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_validation_issues');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_ledger');
        Schema::dropIfExists('order_mappings');
        Schema::dropIfExists('customer_mappings');
        Schema::dropIfExists('product_mappings');
    }
};
