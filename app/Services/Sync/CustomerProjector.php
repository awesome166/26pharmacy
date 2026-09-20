<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CustomerProjector
{
    public function apply(object $event): void
    {
        $payload = is_string($event->event_payload)
            ? json_decode($event->event_payload, true, 512, JSON_THROW_ON_ERROR)
            : $event->event_payload;
        $customer = Validator::make($payload, [
            'customer' => ['required', 'array:id,name,phone,email,dob'],
            'customer.id' => ['required', 'ulid'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.dob' => ['nullable', 'date_format:Y-m-d'],
        ])->validate()['customer'];
        $existing = DB::table('customers')->where('id', $customer['id'])->lockForUpdate()->first();
        if ($existing && (string) $existing->account_id !== (string) $event->account_id) {
            throw ValidationException::withMessages(['customer.id' => 'ENTITY_OWNERSHIP_CONFLICT']);
        }
        $values = [
            'name' => $customer['name'] ?? null, 'phone' => $customer['phone'] ?? null,
            'email' => $customer['email'] ?? null, 'dob' => $customer['dob'] ?? null,
            'updated_at' => $event->event_time_utc,
        ];
        if ($existing) {
            DB::table('customers')->where('id', $customer['id'])->where('account_id', $event->account_id)->update($values);
            return;
        }
        DB::table('customers')->insert($values + [
            'id' => $customer['id'], 'account_id' => $event->account_id,
            'created_at' => $event->event_time_utc,
        ]);
    }
}
