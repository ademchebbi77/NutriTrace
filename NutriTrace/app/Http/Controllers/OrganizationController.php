<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function edit(Request $request): View
    {
        $organization = $request->user()->organization()->firstOrFail();

        Gate::authorize('update', $organization);

        return view('organization.edit', ['organization' => $organization]);
    }

    public function update(OrganizationUpdateRequest $request): RedirectResponse
    {
        $organization = $request->user()->organization()->firstOrFail();

        Gate::authorize('update', $organization);

        $organization->fill($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $previous = $organization->logo_path;
            $organization->logo_path = $request->file('logo')->store('logos', 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        $organization->save();

        return redirect()->route('organization.edit')->with('success', __('account.organization.updated'));
    }
}
