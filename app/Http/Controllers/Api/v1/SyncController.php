<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\SyncEventsToCloudJob;
use App\Jobs\PullCloudEventsJob;
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
        $this->syncService->push();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sync push initiated'
            ], 200);
        }

        return redirect()->back()->with('success', 'Sync push started');
    }

    /**
     * Pull new events from cloud to local branch.
     */
    public function pull(Request $request)
    {
        // Trigger SyncService Pull
        $this->syncService->pull();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sync pull initiated'
            ], 200);
        }

        return redirect()->back()->with('success', 'Sync pull started');
    }
}
