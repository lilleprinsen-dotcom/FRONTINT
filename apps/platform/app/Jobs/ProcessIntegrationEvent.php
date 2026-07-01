<?php

namespace App\Jobs;

use App\Models\Event;
use App\Jobs\RunFrontSaleStockAdjustment;
use App\Services\Sales\FrontSaleImportRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessIntegrationEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $eventId,
    ) {
        $this->onQueue(config('omnibridge.queues.events', 'events'));
    }

    public function handle(FrontSaleImportRecorder $frontSales): void
    {
        $event = Event::query()->findOrFail($this->eventId);

        if ($event->source_system === 'front') {
            $import = $frontSales->recordFromEvent($event);

            if ($import && $import->stock_status === 'pending') {
                RunFrontSaleStockAdjustment::dispatch($import->id);
            }
        }

        $event->update([
            'status' => 'queued',
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Event::query()
            ->whereKey($this->eventId)
            ->update([
                'status' => 'failed',
                'error_class' => $exception::class,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
