<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use AbacPermissions\Tenancy\TenantContext;
use Inertia\Inertia;

class SystemSettingController extends Controller
{
    /**
     * Get all relevant settings for current context.
     */
    public function index(Request $request)
    {
        $result = SystemSetting::getMergedSettings();
        // $accountId = app(TenantContext::class)->getAccountId();
        // $result = SystemSetting::getMergedSettings($accountId);


        if ($request->wantsJson()) {
            return response()->json([
                'config' => $result['settings'],
                'exists' => $result['exists']
            ]);
        }

        return Inertia::render('Config/Index', [
            'config' => $result['settings'],
            'exists' => $result['exists']
        ]);
    }

    /**
     * Store new settings (Alias for update in our case logic, but semantically POST)
     */
    public function store(Request $request)
    {
        // clear cache
        SystemSetting::clearCache();
        return $this->update($request);
    }

    /**
     * Update settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable', // Allow boolean/string/int
        ]);

        // clear cache
        SystemSetting::clearCache();

        try {
            DB::transaction(function () use ($validated) {
                foreach ($validated['settings'] as $key => $value) {
                    // Ensure booleans are stored consistently if strictly required,
                    // but SystemSetting::setValue should handle it.
                    SystemSetting::setValue($key, $value);
                }
            });
        } catch (\InvalidArgumentException $e) {
            // Handle namespace protection violations
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => 'validation_error'
                ], 422);
            }

            return redirect()->back()->withErrors(['settings' => $e->getMessage()]);
        }

        // Optimization: Just call index() logic to get fresh state to return
        // This ensures frontend receives exactly what is in DB
        $freshData = $this->index($request)->getData();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Settings saved successfully.',
                'data' => $freshData->config, // Return the fresh settings
                'exists' => $freshData->exists
            ]);
        }

        return redirect()->back()->with('success', 'Settings saved.');
    }
}
