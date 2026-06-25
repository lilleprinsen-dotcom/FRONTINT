<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('omnibridge:about', function (): void {
    $this->info('OmniBridge platform foundation is installed.');
})->purpose('Show OmniBridge platform information');
