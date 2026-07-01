<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('front_sale_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_mapping_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('front_sale_id')->nullable();
            $table->string('front_receipt_id')->nullable();
            $table->string('idempotency_key');
            $table->timestamp('sale_time')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->json('payload_summary_json')->nullable();
            $table->json('line_items_json')->nullable();
            $table->json('woo_order_payload_json')->nullable();
            $table->json('last_request_summary_json')->nullable();
            $table->json('last_response_summary_json')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['organization_id', 'front_sale_id']);
            $table->index(['organization_id', 'front_receipt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_sale_imports');
    }
};
