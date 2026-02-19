<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'accounts' => $this->whenLoaded('accounts', function () {
                return $this->accounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->name,
                        'plan' => $account->plan ?? null,
                    ];
                });
            }),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'zeus_level' => $role->zeus_level,
                    ];
                });
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($perm) {
                    // The pivot here is the assigned_permissions row
                    $rawAccess = $perm->pivot?->access ?? null;
                    $access = [];
                    if ($rawAccess) {
                        $decoded = is_array($rawAccess) ? $rawAccess : json_decode($rawAccess, true);
                        $access = is_array($decoded) ? $decoded : [];
                    }
                    return [
                        'id'     => $perm->id,
                        'name'   => $perm->name,
                        'type'   => $perm->type,
                        'access' => $access,
                    ];
                });
            }),
        ];
    }
}
