<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('connection_discovery_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('source_system');
            $table->string('discovery_type');
            $table->string('status');
            $table->json('summary_json')->nullable();
            $table->json('sample_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['organization_id', 'source_system', 'discovery_type']);
            $table->index(['connection_id', 'discovery_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_discovery_snapshots');
    }
};
