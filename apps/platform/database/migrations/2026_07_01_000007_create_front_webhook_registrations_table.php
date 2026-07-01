<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('front_webhook_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('front_webhook_id')->nullable();
            $table->string('webhook_type');
            $table->string('callback_url');
            $table->string('status')->default('planned');
            $table->json('request_summary_json')->nullable();
            $table->json('response_summary_json')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'webhook_type'], 'front_webhook_registrations_unique_type');
            $table->index(['organization_id', 'status']);
            $table->index(['connection_id', 'status']);
            $table->index('front_webhook_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_webhook_registrations');
    }
};
