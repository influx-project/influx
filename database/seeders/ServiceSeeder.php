<?php

namespace Database\Seeders;

use App\Enums\ServiceImportance;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Seed a few example services for every existing user, plus some unassigned ones.
     */
    public function run(): void
    {
        User::each(function (User $user): void {
            Service::factory()->for($user, 'owner')->https()->importance(ServiceImportance::Critical)->create();
            Service::factory()->for($user, 'owner')->ping()->create();
            Service::factory()->for($user, 'owner')->count(3)->create();
        });

        Service::factory()->unassigned()->count(2)->create();
    }
}
