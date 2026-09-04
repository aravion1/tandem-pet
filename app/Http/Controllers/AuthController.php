<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivationConfirmRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordResetConfirmRequest;
use App\Http\Requests\PhoneRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OneTimeCodeService;
use App\Services\PhoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthController extends Controller
{
    public function __construct(
        private readonly PhoneService $phones,
        private readonly OneTimeCodeService $codes,
        private readonly AuditLogger $audit,
    ) {}

    public function requestActivation(PhoneRequest $request): JsonResponse
    {
        $phone = $this->phones->normalize($request->string('phone')->toString());
        $user = $this->findUser($phone);

        if ($user === null || $user->activated_at !== null) {
            abort(422, 'Невозможно активировать учётную запись.');
        }

        $this->codes->send($user, $phone, 'activation');

        return response()->json(status: 202);
    }

    public function confirmActivation(ActivationConfirmRequest $request): JsonResponse
    {
        $phone = $this->phones->normalize($request->string('phone')->toString());
        $user = $this->findUser($phone);

        if ($user === null || $user->activated_at !== null || ! $this->codes->consume($user, 'activation', $request->string('code')->toString())) {
            abort(422, 'Неверный или истёкший код.');
        }

        $user->forceFill([
            'password_hash' => Hash::make($request->string('password')->toString()),
            'consented_at' => now(),
            'consent_version' => $request->string('consent_version')->toString(),
            'activated_at' => now(),
        ])->save();
        $this->audit->record($user->id, 'user.activated', 'user', $user->id, ['consent_version' => $user->consent_version]);

        return response()->json(['token' => $user->createToken('api')->plainTextToken, 'token_type' => 'Bearer']);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $phone = $this->phones->normalize($request->string('phone')->toString());
        $rateLimitKey = 'login:'.$this->phones->hash($phone);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            throw new TooManyRequestsHttpException(900, 'Превышен лимит попыток входа.');
        }

        $user = $this->findUser($phone);

        if ($user === null || $user->activated_at === null || ! Hash::check($request->string('password')->toString(), $user->password_hash ?? '')) {
            RateLimiter::hit($rateLimitKey, 900);
            abort(401, 'Неверный номер телефона или пароль.');
        }

        RateLimiter::clear($rateLimitKey);

        return response()->json(['token' => $user->createToken('api')->plainTextToken, 'token_type' => 'Bearer']);
    }

    public function requestPasswordReset(PhoneRequest $request): JsonResponse
    {
        $phone = $this->phones->normalize($request->string('phone')->toString());
        $user = $this->findUser($phone);

        if ($user !== null && $user->activated_at !== null) {
            $this->codes->send($user, $phone, 'password_reset');
        }

        return response()->json(status: 202);
    }

    public function confirmPasswordReset(PasswordResetConfirmRequest $request): JsonResponse
    {
        $phone = $this->phones->normalize($request->string('phone')->toString());
        $user = $this->findUser($phone);

        if ($user === null || $user->activated_at === null || ! $this->codes->consume($user, 'password_reset', $request->string('code')->toString())) {
            abort(422, 'Неверный или истёкший код.');
        }

        $user->forceFill(['password_hash' => Hash::make($request->string('password')->toString())])->save();
        $user->tokens()->delete();
        $this->audit->record($user->id, 'user.password_reset', 'user', $user->id);

        return response()->json(['token' => $user->createToken('api')->plainTextToken, 'token_type' => 'Bearer']);
    }

    private function findUser(string $normalizedPhone): ?User
    {
        return User::query()->where('phone_hash', $this->phones->hash($normalizedPhone))->first();
    }
}
