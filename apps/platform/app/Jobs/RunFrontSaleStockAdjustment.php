<?php

namespace App\Jobs;

use App\Models\FrontSaleImport;
use App\Models\User;
use App\Services\Sales\FrontSaleStockAdjustmentRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunFrontSaleStockAdjustment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $frontSaleImportId,
        public readonly ?int $userId = null,
    ) {
        $this->onQueue(config('omnibridge.queues.sync', 'sync'));
    }

    public function handle(FrontSaleStockAdjustmentRunner $runner): void
    {
        $runner->run(
            FrontSaleImport::query()->findOrFail($this->frontSaleImportId),
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }
}
