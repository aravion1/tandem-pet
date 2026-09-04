<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PhoneService;
use App\Services\ResidentService;
use App\Services\TestSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_resident_activates_and_logs_in_without_exposing_phone(): void
    {
        $phone = '+79990001122';
        $this->importResident($phone);

        $this->postJson('/api/auth/activation/request', ['phone' => $phone])->assertAccepted();
        $code = app(TestSmsSender::class)->latestCode($phone);

        $activation = $this->postJson('/api/auth/activation/confirm', [
            'phone' => $phone,
            'code' => $code,
            'password' => 'strong-password-123',
            'consent_version' => 'v1',
        ]);

        $activation->assertOk()->assertJsonStructure(['token', 'token_type'])->assertJsonMissing(['phone', 'phone_hash', 'password_hash']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activated']);
        $this->assertDatabaseMissing('audit_logs', ['payload' => json_encode(['phone' => $phone])]);

        $this->postJson('/api/auth/login', ['phone' => '8 (999) 000-11-22', 'password' => 'strong-password-123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type']);
    }

    public function test_non_imported_phone_cannot_be_activated(): void
    {
        $this->postJson('/api/auth/activation/request', ['phone' => '+79990001122'])->assertUnprocessable();
    }

    public function test_activation_code_is_limited_to_five_failed_attempts(): void
    {
        $phone = '+79990001122';
        $this->importResident($phone);
        $this->postJson('/api/auth/activation/request', ['phone' => $phone])->assertAccepted();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/activation/confirm', [
                'phone' => $phone,
                'code' => '000000',
                'password' => 'strong-password-123',
                'consent_version' => 'v1',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/activation/confirm', [
            'phone' => $phone,
            'code' => app(TestSmsSender::class)->latestCode($phone),
            'password' => 'strong-password-123',
            'consent_version' => 'v1',
        ])->assertUnprocessable();
    }

    public function test_code_requests_are_rate_limited_per_phone(): void
    {
        $phone = '+79990001122';
        $this->importResident($phone);

        for ($request = 0; $request < 3; $request++) {
            $this->postJson('/api/auth/activation/request', ['phone' => $phone])->assertAccepted();
        }

        $this->postJson('/api/auth/activation/request', ['phone' => $phone])->assertStatus(429);
    }

    public function test_password_reset_does_not_reveal_account_existence(): void
    {
        $this->postJson('/api/auth/password/reset/request', ['phone' => '+79990001122'])->assertAccepted();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $phone = '+79990001122';
        $this->activateResident($phone);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', ['phone' => $phone, 'password' => 'wrong-password'])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', ['phone' => $phone, 'password' => 'strong-password-123'])->assertStatus(429);
    }

    public function test_password_reset_replaces_credentials_and_revokes_old_token(): void
    {
        $phone = '+79990001122';
        $user = $this->activateResident($phone);
        $oldToken = $user->createToken('api')->plainTextToken;

        $this->postJson('/api/auth/password/reset/request', ['phone' => $phone])->assertAccepted();
        $code = app(TestSmsSender::class)->latestCode($phone);
        $response = $this->postJson('/api/auth/password/reset/confirm', [
            'phone' => $phone,
            'code' => $code,
            'password' => 'another-password-123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'token_type']);
        $this->withToken($oldToken)->getJson('/api/roles')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['phone' => $phone, 'password' => 'another-password-123'])->assertOk();
    }

    public function test_protected_management_endpoint_rejects_user_without_permission(): void
    {
        $user = $this->activateResident('+79990001122');
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/users/import', ['residents' => []])->assertForbidden();
    }

    public function test_authorized_user_can_import_residents_and_manage_roles(): void
    {
        $manager = $this->activateResident('+79990001122');
        $roleId = DB::table('roles')->where('name', 'Председатель правления')->value('id');
        DB::table('user_roles')->insert(['user_id' => $manager->id, 'role_id' => $roleId]);
        DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_code' => 'users.manage']);
        $token = $manager->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/users/import', ['residents' => [[
            'phone' => '+79990002233', 'street' => 'Лесная', 'house' => '10',
        ]]])->assertAccepted();

        $created = $this->withToken($token)->postJson('/api/roles', ['name' => 'Диспетчер'])->assertCreated();
        $roleId = $created->json('id');
        $this->withToken($token)->putJson("/api/roles/{$roleId}/permissions", ['permissions' => ['tasks.view']])->assertOk();
        $this->assertDatabaseHas('role_permissions', ['role_id' => $roleId, 'permission_code' => 'tasks.view']);
    }

    public function test_authorized_user_can_change_phone_without_writing_it_to_audit(): void
    {
        $manager = $this->activateResident('+79990001122');
        $resident = $this->activateResident('+79990002233');
        $chairRoleId = DB::table('roles')->where('name', 'Председатель правления')->value('id');
        DB::table('user_roles')->insert(['user_id' => $manager->id, 'role_id' => $chairRoleId]);
        DB::table('role_permissions')->insertOrIgnore(['role_id' => $chairRoleId, 'permission_code' => 'users.manage']);

        $this->withToken($manager->createToken('api')->plainTextToken)
            ->patchJson("/api/users/{$resident->id}/phone", ['phone' => '+79990003344'])
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.phone_changed', 'entity_id' => $resident->id]);
        $this->assertDatabaseMissing('audit_logs', ['payload' => json_encode(['phone' => '+79990003344'])]);
    }

    private function importResident(string $phone): User
    {
        return app(ResidentService::class)->import(['phone' => $phone, 'street' => 'Лесная', 'house' => '1'], null);
    }

    private function activateResident(string $phone): User
    {
        $this->importResident($phone);
        $this->postJson('/api/auth/activation/request', ['phone' => $phone]);
        $code = app(TestSmsSender::class)->latestCode($phone);
        $this->postJson('/api/auth/activation/confirm', [
            'phone' => $phone,
            'code' => $code,
            'password' => 'strong-password-123',
            'consent_version' => 'v1',
        ])->assertOk();

        return User::query()->where('phone_hash', app(PhoneService::class)->hash($phone))->firstOrFail();
    }
}
