<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_sales')) {
            Schema::create('customer_sales', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('account_id')->nullable()->index();
                $table->ulid('customer_id')->index();
                $table->ulid('sale_id')->unique();
                $table->timestamps();
            });
        }

        $hasCustomerId = Schema::hasColumn('sales', 'customer_id');
        $hasCustomerName = Schema::hasColumn('sales', 'customer_name');
        $hasCustomerDob = Schema::hasColumn('sales', 'customer_dob');
        $hasCustomerPhone = Schema::hasColumn('sales', 'customer_phone');
        $hasCustomerEmail = Schema::hasColumn('sales', 'customer_email');

        if ($hasCustomerId || $hasCustomerName || $hasCustomerDob || $hasCustomerPhone || $hasCustomerEmail) {
            DB::table('sales')
                ->select([
                    'id',
                    'account_id',
                    ...($hasCustomerId ? ['customer_id'] : []),
                    ...($hasCustomerName ? ['customer_name'] : []),
                    ...($hasCustomerDob ? ['customer_dob'] : []),
                    ...($hasCustomerPhone ? ['customer_phone'] : []),
                    ...($hasCustomerEmail ? ['customer_email'] : []),
                ])
                ->orderBy('id')
                ->chunk(200, function ($sales) use ($hasCustomerId, $hasCustomerName, $hasCustomerDob, $hasCustomerPhone, $hasCustomerEmail) {
                    foreach ($sales as $sale) {
                        $customerId = $hasCustomerId ? ($sale->customer_id ?? null) : null;

                        $name = $hasCustomerName ? ($sale->customer_name ?? null) : null;
                        $dob = $hasCustomerDob ? ($sale->customer_dob ?? null) : null;
                        $phone = $hasCustomerPhone ? ($sale->customer_phone ?? null) : null;
                        $email = $hasCustomerEmail ? ($sale->customer_email ?? null) : null;

                        if (!$customerId && ($name || $dob || $phone || $email)) {
                            $customerQuery = DB::table('customers')->where('account_id', $sale->account_id);

                            if ($phone) {
                                $customerQuery->where('phone', $phone);
                            } elseif ($email) {
                                $customerQuery->where('email', $email);
                            } else {
                                $customerQuery->where('name', $name);
                                if ($dob) {
                                    $customerQuery->whereDate('dob', $dob);
                                }
                            }

                            $existingCustomer = $customerQuery->first();

                            if ($existingCustomer) {
                                $customerId = $existingCustomer->id;
                                DB::table('customers')
                                    ->where('id', $customerId)
                                    ->update([
                                        'name' => $name ?: $existingCustomer->name,
                                        'phone' => $phone ?: $existingCustomer->phone,
                                        'email' => $email ?: $existingCustomer->email,
                                        'dob' => $dob ?: $existingCustomer->dob,
                                        'updated_at' => now(),
                                    ]);
                            } else {
                                $customerId = (string) Str::ulid();
                                DB::table('customers')->insert([
                                    'id' => $customerId,
                                    'account_id' => $sale->account_id,
                                    'name' => $name,
                                    'dob' => $dob,
                                    'phone' => $phone,
                                    'email' => $email,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }

                        if ($customerId) {
                            $existingLink = DB::table('customer_sales')->where('sale_id', $sale->id)->first();
                            if ($existingLink) {
                                DB::table('customer_sales')->where('sale_id', $sale->id)->update([
                                    'customer_id' => $customerId,
                                    'account_id' => $sale->account_id,
                                    'updated_at' => now(),
                                ]);
                            } else {
                                DB::table('customer_sales')->insert([
                                    'id' => (string) Str::ulid(),
                                    'account_id' => $sale->account_id,
                                    'customer_id' => $customerId,
                                    'sale_id' => $sale->id,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                });
        }

        if (DB::getDriverName() === 'sqlite') {
            // SQLite table-rebuild edge cases can fail on legacy indexes; keep legacy columns
            // but all runtime reads/writes now use customers + customer_sales.
            return;
        }

        if ($hasCustomerId) {
            try {
                Schema::table('sales', function (Blueprint $table) {
                    $table->dropIndex(['customer_id']);
                });
            } catch (\Throwable $e) {
                // Ignore missing index on sqlite variants.
            }
        }

        $dropColumns = [];
        foreach (['customer_id', 'customer_name', 'customer_dob', 'customer_phone', 'customer_email'] as $column) {
            if (Schema::hasColumn('sales', $column)) {
                $dropColumns[] = $column;
            }
        }

        if (!empty($dropColumns)) {
            Schema::table('sales', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }
    }

    public function down(): void
    {
        $needsCustomerId = !Schema::hasColumn('sales', 'customer_id');
        $needsCustomerName = !Schema::hasColumn('sales', 'customer_name');
        $needsCustomerDob = !Schema::hasColumn('sales', 'customer_dob');
        $needsCustomerPhone = !Schema::hasColumn('sales', 'customer_phone');
        $needsCustomerEmail = !Schema::hasColumn('sales', 'customer_email');

        if ($needsCustomerId || $needsCustomerName || $needsCustomerDob || $needsCustomerPhone || $needsCustomerEmail) {
            Schema::table('sales', function (Blueprint $table) use (
                $needsCustomerId,
                $needsCustomerName,
                $needsCustomerDob,
                $needsCustomerPhone,
                $needsCustomerEmail
            ) {
                if ($needsCustomerId) {
                    $table->ulid('customer_id')->nullable()->after('user_id');
                    $table->index('customer_id');
                }
                if ($needsCustomerName) {
                    $table->string('customer_name')->nullable();
                }
                if ($needsCustomerDob) {
                    $table->date('customer_dob')->nullable();
                }
                if ($needsCustomerPhone) {
                    $table->string('customer_phone')->nullable();
                }
                if ($needsCustomerEmail) {
                    $table->string('customer_email')->nullable();
                }
            });
        }

        DB::table('customer_sales')
            ->join('customers', 'customers.id', '=', 'customer_sales.customer_id')
            ->select('customer_sales.sale_id', 'customers.id as customer_id', 'customers.name', 'customers.dob', 'customers.phone', 'customers.email')
            ->orderBy('customer_sales.sale_id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('sales')->where('id', $row->sale_id)->update([
                        'customer_id' => $row->customer_id,
                        'customer_name' => $row->name,
                        'customer_dob' => $row->dob,
                        'customer_phone' => $row->phone,
                        'customer_email' => $row->email,
                    ]);
                }
            });

        Schema::dropIfExists('customer_sales');
    }
};
