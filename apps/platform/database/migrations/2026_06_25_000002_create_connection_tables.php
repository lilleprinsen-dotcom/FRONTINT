<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'type']);
        });

        Schema::create('connection_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('credential_type');
            $table->text('encrypted_payload');
            $table->string('redacted_hint')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
            $table->index(['connection_id', 'credential_type']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_system');
            $table->string('path_token')->unique();
            $table->text('encrypted_secret')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['organization_id', 'source_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('connection_credentials');
        Schema::dropIfExists('connections');
    }
};
