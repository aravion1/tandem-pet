<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BootstrapChairmanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_exactly_one_chairman_for_normal_activation(): void
    {
        $this->artisan('users:bootstrap-chairman', [
            'phone' => '+79990001122',
            'street' => 'Лесная',
            'house' => '1',
        ])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.bootstrap_chairman']);
        $this->assertSame(1, DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.name', 'Председатель правления')
            ->count());

        $this->artisan('users:bootstrap-chairman', [
            'phone' => '+79990002233',
            'street' => 'Лесная',
            'house' => '2',
        ])->assertFailed();
    }
}
