<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('sync.tenant.{tenantId}', function ($user, $tenantId) {
    // In a real app, verify $user belongs to $tenantId
    // For now, we assume auth middleware handled it or we check user's account_id

    // Return data for the presence channel "who's here" list
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'device' => request()->header('User-Agent') ?? 'Unknown Device',
        'role' => env('SYNC_ROLE', 'unknown'), // specific to that connection's server role if applicable
        'client_id' => request()->header('X-Sync-Client-Id') ?? env('SYNC_CLIENT_ID'),
    ];
});
