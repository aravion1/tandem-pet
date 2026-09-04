<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class OneTimeCodeService
{
    public function __construct(private readonly SmsSender $smsSender, private readonly PhoneService $phones) {}

    public function send(User $user, string $normalizedPhone, string $purpose): void
    {
        $key = "sms:{$purpose}:{$this->phones->hash($normalizedPhone)}";

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw new TooManyRequestsHttpException(900, 'Превышен лимит запросов кода.');
        }

        RateLimiter::hit($key, 900);
        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $purpose, $code): void {
            DB::table('one_time_codes')
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            DB::table('one_time_codes')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
            ]);
        });

        $this->smsSender->send($normalizedPhone, $code);
    }

    public function consume(User $user, string $purpose, string $code): bool
    {
        return DB::transaction(function () use ($user, $purpose, $code): bool {
            $record = DB::table('one_time_codes')
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->attempts >= 5) {
                return false;
            }

            if (! Hash::check($code, $record->code_hash)) {
                DB::table('one_time_codes')->where('id', $record->id)->increment('attempts');

                return false;
            }

            DB::table('one_time_codes')->where('id', $record->id)->update(['consumed_at' => now()]);

            return true;
        });
    }
}
