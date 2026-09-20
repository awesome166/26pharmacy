<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SyncController extends Controller
{
    protected $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Push a batch of events from branch to cloud.
     */
    public function push(Request $request)
    {
        // Trigger SyncService Push
        $result = $this->syncService->push();

        if ($request->wantsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 502);
        }

        return redirect()->back()->with('success', 'Sync push started');
    }

    /**
     * Pull new events from cloud to local branch.
     */
    public function pull(Request $request)
    {
        // Trigger SyncService Pull
        $result = $this->syncService->pull();

        if ($request->wantsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 502);
        }

        return redirect()->back()->with('success', 'Sync pull started');
    }

    /**
     * Full restore from cloud - wipes local read models and replays all events.
     */
    public function restore(Request $request)
    {
        $result = $this->syncService->restoreFromCloud();

        if ($request->wantsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 502);
        }

        return redirect()->back()->with('success', 'Full restore completed');
    }
}
