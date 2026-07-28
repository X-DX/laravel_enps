<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 36 Indian states / union territories using the official Government-of-India
 * LGD (Local Government Directory) codes — the same source the eNPS data comes from.
 *
 * `upsert()` makes this idempotent: run it once or ten times, you always end up with
 * exactly these 36 rows (matched on the primary key `state_code`), so it is safe to
 * re-run after a deploy.
 */
class StateMasterSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            35 => 'Andaman And Nicobar Islands',
            28 => 'Andhra Pradesh',
            12 => 'Arunachal Pradesh',
            18 => 'Assam',
            10 => 'Bihar',
            4 => 'Chandigarh',
            22 => 'Chhattisgarh',
            7 => 'Delhi',
            30 => 'Goa',
            24 => 'Gujarat',
            6 => 'Haryana',
            2 => 'Himachal Pradesh',
            1 => 'Jammu And Kashmir',
            20 => 'Jharkhand',
            29 => 'Karnataka',
            32 => 'Kerala',
            37 => 'Ladakh',
            31 => 'Lakshadweep',
            23 => 'Madhya Pradesh',
            27 => 'Maharashtra',
            14 => 'Manipur',
            17 => 'Meghalaya',
            15 => 'Mizoram',
            13 => 'Nagaland',
            21 => 'Odisha',
            34 => 'Puducherry',
            3 => 'Punjab',
            8 => 'Rajasthan',
            11 => 'Sikkim',
            33 => 'Tamil Nadu',
            36 => 'Telangana',
            38 => 'The Dadra And Nagar Haveli And Daman And Diu',
            16 => 'Tripura',
            5 => 'Uttarakhand',
            9 => 'Uttar Pradesh',
            19 => 'West Bengal',
        ];

        $rows = [];
        foreach ($states as $code => $name) {
            $rows[] = ['state_code' => $code, 'state_name' => $name];
        }

        // Match on state_code; refresh state_name if it ever changes.
        DB::table('state_master')->upsert($rows, ['state_code'], ['state_name']);
    }
}
