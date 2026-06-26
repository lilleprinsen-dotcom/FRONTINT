<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use App\Services\Webhooks\WebhookEndpointProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        return view('organizations.index', [
            'organizations' => $request->user()
                ->organizations()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('organizations.form', [
            'organization' => new Organization([
                'environment' => 'staging',
                'status' => 'active',
            ]),
        ]);
    }

    public function store(OrganizationRequest $request, WebhookEndpointProvisioner $webhooks): RedirectResponse
    {
        $organization = Organization::query()->create($request->validated());
        $organization->users()->attach($request->user()->id, ['role' => 'owner']);
        $webhooks->ensureDefaults($organization);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Organization created.');
    }

    public function edit(Request $request, Organization $organization): View
    {
        $this->authorizeMembership($request, $organization);

        return view('organizations.form', [
            'organization' => $organization,
        ]);
    }

    public function update(
        OrganizationRequest $request,
        Organization $organization,
        WebhookEndpointProvisioner $webhooks,
    ): RedirectResponse {
        $this->authorizeMembership($request, $organization);

        $organization->update($request->validated());
        $webhooks->ensureDefaults($organization);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Organization updated.');
    }

    private function authorizeMembership(Request $request, Organization $organization): void
    {
        abort_unless(
            $request->user()->organizations()->whereKey($organization->id)->exists(),
            403,
        );
    }
}
