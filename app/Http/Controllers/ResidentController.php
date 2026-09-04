<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportResidentsRequest;
use App\Http\Requests\PhoneRequest;
use App\Models\User;
use App\Services\ResidentService;
use Illuminate\Http\JsonResponse;

class ResidentController extends Controller
{
    public function __construct(private readonly ResidentService $residents) {}

    public function import(ImportResidentsRequest $request): JsonResponse
    {
        foreach ($request->validated('residents') as $resident) {
            $this->residents->import($resident, $request->user()->id);
        }

        return response()->json(status: 202);
    }

    public function changePhone(PhoneRequest $request, User $user): JsonResponse
    {
        $this->residents->changePhone($user, $request->string('phone')->toString(), $request->user()->id);

        return response()->json(status: 204);
    }
}
