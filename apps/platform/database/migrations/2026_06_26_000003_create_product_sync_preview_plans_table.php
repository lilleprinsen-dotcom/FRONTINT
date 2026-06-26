<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_sync_preview_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('woo_connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->foreignId('front_connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->string('status');
            $table->unsignedTinyInteger('selected_count');
            $table->json('summary_json')->nullable();
            $table->json('plan_json')->nullable();
            $table->json('validation_json')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sync_preview_plans');
    }
};
