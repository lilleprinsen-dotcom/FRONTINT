<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_card_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('gift_card_code_hash');
            $table->string('operation');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('source_system');
            $table->string('source_reference')->nullable();
            $table->string('status')->default('pending');
            $table->string('idempotency_key');
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'gift_card_code_hash', 'created_at']);
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('sync_type');
            $table->string('status')->default('pending');
            $table->text('cursor')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('items_seen')->default(0);
            $table->unsignedInteger('items_succeeded')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'sync_type', 'status']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value_json')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('gift_card_transactions');
    }
};
