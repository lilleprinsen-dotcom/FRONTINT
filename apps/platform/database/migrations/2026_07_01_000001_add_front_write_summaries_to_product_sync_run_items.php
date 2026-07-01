<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->json('last_request_summary_json')->nullable()->after('last_error');
            $table->json('last_response_summary_json')->nullable()->after('last_request_summary_json');
        });
    }

    public function down(): void
    {
        Schema::table('product_sync_run_items', function (Blueprint $table): void {
            $table->dropColumn(['last_request_summary_json', 'last_response_summary_json']);
        });
    }
};
