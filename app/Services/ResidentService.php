<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResidentService
{
    public function __construct(private readonly PhoneService $phones, private readonly AuditLogger $audit) {}

    /** @param array{phone:string,street:string,house:string} $data */
    public function import(array $data, ?string $actorId): User
    {
        $phone = $this->phones->normalize($data['phone']);
        $hash = $this->phones->hash($phone);

        return DB::transaction(function () use ($data, $actorId, $phone, $hash): User {
            $user = User::query()->where('phone_hash', $hash)->lockForUpdate()->first();
            $created = $user === null;

            if ($user === null) {
                $user = User::query()->create([
                    'id' => (string) Str::uuid(),
                    'phone_ciphertext' => $this->phones->encrypt($phone),
                    'phone_hash' => $hash,
                ]);
            }

            $street = DB::table('streets')->where('name', $data['street'])->first();
            $streetId = $street?->id ?? (string) Str::uuid();
            if ($street === null) {
                DB::table('streets')->insert(['id' => $streetId, 'name' => $data['street']]);
            }

            $plot = DB::table('plots')->where('street_id', $streetId)->where('house', $data['house'])->first();
            $plotId = $plot?->id ?? (string) Str::uuid();
            if ($plot === null) {
                DB::table('plots')->insert(['id' => $plotId, 'street_id' => $streetId, 'house' => $data['house']]);
            }

            DB::table('user_plots')->insertOrIgnore(['user_id' => $user->id, 'plot_id' => $plotId]);
            $residentRoleId = DB::table('roles')->where('name', 'Житель')->value('id');
            DB::table('user_roles')->insertOrIgnore(['user_id' => $user->id, 'role_id' => $residentRoleId]);

            $this->audit->record($actorId, $created ? 'user.imported' : 'user.plot_attached', 'user', $user->id, ['source' => 'import']);

            return $user;
        });
    }

    public function changePhone(User $user, string $phone, string $actorId): void
    {
        $normalized = $this->phones->normalize($phone);
        $hash = $this->phones->hash($normalized);

        if (User::query()->where('phone_hash', $hash)->where('id', '!=', $user->id)->exists()) {
            abort(409, 'Этот номер уже используется.');
        }

        $user->forceFill([
            'phone_ciphertext' => $this->phones->encrypt($normalized),
            'phone_hash' => $hash,
        ])->save();
        $user->tokens()->delete();
        $this->audit->record($actorId, 'user.phone_changed', 'user', $user->id);
    }
}
