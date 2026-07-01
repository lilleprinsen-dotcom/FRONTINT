<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('front_sale_imports', function (Blueprint $table): void {
            $table->string('handling_mode')->default('stock_only')->after('status');
            $table->string('stock_status')->default('pending')->after('handling_mode');
            $table->text('stock_error_message')->nullable()->after('stock_status');
            $table->unsignedInteger('stock_attempt_count')->default(0)->after('stock_error_message');
            $table->json('stock_request_summary_json')->nullable()->after('stock_attempt_count');
            $table->json('stock_response_summary_json')->nullable()->after('stock_request_summary_json');
            $table->timestamp('stock_adjusted_at')->nullable()->after('stock_response_summary_json');
            $table->string('order_import_status')->default('not_imported')->after('stock_adjusted_at');

            $table->index(['organization_id', 'stock_status']);
            $table->index(['organization_id', 'order_import_status']);
        });
    }

    public function down(): void
    {
        Schema::table('front_sale_imports', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'stock_status']);
            $table->dropIndex(['organization_id', 'order_import_status']);
            $table->dropColumn([
                'handling_mode',
                'stock_status',
                'stock_error_message',
                'stock_attempt_count',
                'stock_request_summary_json',
                'stock_response_summary_json',
                'stock_adjusted_at',
                'order_import_status',
            ]);
        });
    }
};
