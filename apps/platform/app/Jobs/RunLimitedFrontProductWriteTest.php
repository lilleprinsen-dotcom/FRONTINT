<?php

namespace App\Jobs;

use App\Models\ProductSyncRun;
use App\Models\User;
use App\Services\ProductSync\LimitedFrontProductWriteRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunLimitedFrontProductWriteTest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param array<int, int> $itemIds
     */
    public function __construct(
        public readonly int $runId,
        public readonly int $userId,
        public readonly array $itemIds,
    ) {
        $this->onQueue(config('omnibridge.queues.product_sync', 'product-sync'));
    }

    public function handle(LimitedFrontProductWriteRunner $runner): void
    {
        $run = ProductSyncRun::query()->findOrFail($this->runId);
        $user = User::query()->findOrFail($this->userId);

        $runner->run($run, $user, $this->itemIds);
    }
}
