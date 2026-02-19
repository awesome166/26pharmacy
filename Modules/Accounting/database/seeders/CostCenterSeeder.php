<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\CostCenter;
use App\Models\User;

class CostCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clinical Departments
        $clinical = CostCenter::create(['code' => 'CLIN', 'name' => 'Clinical Services', 'type' => 'Clinical']);
        CostCenter::create(['code' => 'EMER', 'name' => 'Emergency', 'type' => 'Clinical', 'parent_id' => $clinical->id]);
        CostCenter::create(['code' => 'SURG', 'name' => 'Surgery', 'type' => 'Clinical', 'parent_id' => $clinical->id]);
        CostCenter::create(['code' => 'PEDS', 'name' => 'Pediatrics', 'type' => 'Clinical', 'parent_id' => $clinical->id]);

        // Administrative
        $admin = CostCenter::create(['code' => 'ADMIN', 'name' => 'Administrative', 'type' => 'Administrative']);
        CostCenter::create(['code' => 'HR', 'name' => 'Human Resources', 'type' => 'Administrative', 'parent_id' => $admin->id]);
        CostCenter::create(['code' => 'FIN', 'name' => 'Finance', 'type' => 'Administrative', 'parent_id' => $admin->id]);
        CostCenter::create(['code' => 'IT', 'name' => 'Information Technology', 'type' => 'Administrative', 'parent_id' => $admin->id]);

        // Support
        $support = CostCenter::create(['code' => 'SUPP', 'name' => 'Support Services', 'type' => 'Support']);
        CostCenter::create(['code' => 'PHARM', 'name' => 'Pharmacy', 'type' => 'Support', 'parent_id' => $support->id]);
        CostCenter::create(['code' => 'LAB', 'name' => 'Laboratory', 'type' => 'Support', 'parent_id' => $support->id]);
        CostCenter::create(['code' => 'RAD', 'name' => 'Radiology', 'type' => 'Support', 'parent_id' => $support->id]);
    }
}
