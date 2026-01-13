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
        // For offline-first: dispatches local events to cloud
        SyncEventsToCloudJob::dispatch($request->header('X-Branch-Id'));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sync outbox processing started'
            ], 202);
        }

        return redirect()->back()->with('success', 'Sync started');
    }

    /**
     * Pull new events from cloud to local branch.
     */
    public function pull(Request $request)
    {
        PullCloudEventsJob::dispatch($request->header('X-Branch-Id'));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sync inbox processing started'
            ], 202);
        }

        return redirect()->back()->with('success', 'Sync started');
    }
}
