<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table): void {
            $table->string('last_test_status')->nullable()->after('last_checked_at');
            $table->unsignedInteger('last_http_status')->nullable()->after('last_test_status');
            $table->unsignedInteger('last_response_time_ms')->nullable()->after('last_http_status');
            $table->text('last_error')->nullable()->after('last_response_time_ms');
            $table->json('last_test_metadata')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table): void {
            $table->dropColumn([
                'last_test_status',
                'last_http_status',
                'last_response_time_ms',
                'last_error',
                'last_test_metadata',
            ]);
        });
    }
};
