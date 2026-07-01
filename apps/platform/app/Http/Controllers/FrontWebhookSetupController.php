<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Services\FrontSystems\FrontWebhookSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FrontWebhookSetupController extends Controller
{
    public function register(
        Request $request,
        Connection $connection,
        FrontWebhookSetupService $setup,
    ): RedirectResponse {
        $this->authorizeConnection($request, $connection);

        $validated = $request->validate([
            'webhook_types' => ['required', 'array', 'min:1', 'max:10'],
            'webhook_types.*' => ['required', 'string', 'max:120'],
        ]);

        $result = $setup->registerSelected(
            $connection,
            $request->user(),
            $validated['webhook_types'],
        );

        $status = $result['status'] === 'success'
            ? 'Front webhook setup completed.'
            : 'Front webhook setup failed: ' . $result['message'];

        return redirect()
            ->route('connections.discovery', $connection)
            ->with($result['status'] === 'success' ? 'status' : 'error_status', $status);
    }

    private function authorizeConnection(Request $request, Connection $connection): void
    {
        abort_unless(
            $request->user()->organizations()->whereKey($connection->organization_id)->exists(),
            403,
        );
    }
}
