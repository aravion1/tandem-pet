<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscussionMembersRequest;
use App\Http\Requests\DiscussionMessageRequest;
use App\Http\Requests\DiscussionVerdictRequest;
use App\Http\Requests\StoreDiscussionRequest;
use App\Http\Requests\UpdateDiscussionRequest;
use App\Models\Discussion;
use App\Models\Message;
use App\Models\User;
use App\Services\DiscussionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    public function __construct(private readonly DiscussionService $discussions) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->discussions->index($this->user($request)));
    }

    public function store(StoreDiscussionRequest $request): JsonResponse
    {
        $discussion = $this->discussions->create($this->user($request), $request->validated());

        return response()->json($this->discussions->present($discussion), 201);
    }

    public function show(Request $request, Discussion $discussion): JsonResponse
    {
        return response()->json($this->discussions->show($this->user($request), $discussion));
    }

    public function update(UpdateDiscussionRequest $request, Discussion $discussion): JsonResponse
    {
        return response()->json($this->discussions->present($this->discussions->update($this->user($request), $discussion, $request->string('title')->toString())));
    }

    public function members(DiscussionMembersRequest $request, Discussion $discussion): JsonResponse
    {
        $this->discussions->addMembers($this->user($request), $discussion, $request->validated('user_ids'));

        return response()->json($this->discussions->present($discussion));
    }

    public function message(DiscussionMessageRequest $request, Discussion $discussion): JsonResponse
    {
        $message = $this->discussions->addMessage($this->user($request), $discussion, $request->string('body')->toString());

        return response()->json(['id' => $message->id], 201);
    }

    public function like(Request $request, Message $message): JsonResponse
    {
        $this->discussions->like($this->user($request), $message);

        return response()->json(status: 201);
    }

    public function verdict(DiscussionVerdictRequest $request, Discussion $discussion): JsonResponse
    {
        $this->discussions->verdict($this->user($request), $discussion, $request->string('message_id')->toString());

        return response()->json($this->discussions->present($discussion));
    }

    public function close(Request $request, Discussion $discussion): JsonResponse
    {
        $this->discussions->close($this->user($request), $discussion);

        return response()->json($this->discussions->present($discussion));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
