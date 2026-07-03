<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    /**
     * Display the admin settings panel.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('admin');

        $settings = [
            'system_name' => Setting::get('system_name', 'Project Tracker'),
            'theme' => Setting::get('theme', 'corporate_teal'),
            'logo' => Setting::get('logo', null),
        ];

        return Inertia::render('Admin/Settings', compact('settings'));
    }

    /**
     * Update the system settings.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $validated = $request->validate([
            'system_name' => 'required|string|max:255',
            'theme' => 'required|in:refined_indigo,corporate_teal,modern_midnight',
            'logo' => 'nullable|image|max:2048', // max 2MB
            'remove_logo' => 'nullable|boolean',
        ]);

        Setting::set('system_name', $validated['system_name']);
        Setting::set('theme', $validated['theme']);

        if (!empty($validated['remove_logo'])) {
            Setting::set('logo', null);
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $data = base64_encode(file_get_contents($file->getRealPath()));
            $mime = $file->getMimeType();
            $logoData = "data:{$mime};base64,{$data}";
            Setting::set('logo', $logoData);
        }

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }
}
