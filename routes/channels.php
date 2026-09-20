<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('sync.tenant.{tenantId}', function ($user, $tenantId) {
    if (!(method_exists($user, 'isZeus') && $user->isZeus())
        && !$user->accounts()->whereKey($tenantId)->exists()) {
        return false;
    }

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
