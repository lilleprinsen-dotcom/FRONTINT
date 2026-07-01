<?php

namespace App\Jobs;

use App\Models\FrontSaleImport;
use App\Models\User;
use App\Services\Sales\FrontSaleImportRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunFrontSaleImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $frontSaleImportId,
        public readonly int $userId,
    ) {
        $this->onQueue(config('omnibridge.queues.sync', 'sync'));
    }

    public function handle(FrontSaleImportRunner $runner): void
    {
        $runner->run(
            FrontSaleImport::query()->findOrFail($this->frontSaleImportId),
            User::query()->findOrFail($this->userId),
        );
    }
}
