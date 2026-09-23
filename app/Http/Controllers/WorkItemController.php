<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkItemRequest;
use App\Http\Requests\UpdateWorkItemRequest;
use App\Http\Requests\WorkItemAssigneesRequest;
use App\Http\Requests\WorkItemAttachmentRequest;
use App\Http\Requests\WorkItemBudgetEntryRequest;
use App\Http\Requests\WorkItemCommentRequest;
use App\Http\Requests\WorkItemConvertRequest;
use App\Http\Requests\WorkItemTimeEntryRequest;
use App\Http\Requests\WorkItemTransitionRequest;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\WorkItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    public function __construct(private readonly WorkItemService $workItems) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->workItems->index($this->user($request)));
    }

    public function store(StoreWorkItemRequest $request): JsonResponse
    {
        $item = $this->workItems->create($this->user($request), $request->validated());

        return response()->json($this->workItems->present($item), 201);
    }

    public function show(Request $request, WorkItem $workItem): JsonResponse
    {
        return response()->json($this->workItems->show($this->user($request), $workItem));
    }

    public function update(UpdateWorkItemRequest $request, WorkItem $workItem): JsonResponse
    {
        $item = $this->workItems->update($this->user($request), $workItem, $request->validated());

        return response()->json($this->workItems->present($item));
    }

    public function comment(WorkItemCommentRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->workItems->addComment($this->user($request), $workItem, $request->string('body')->toString());

        return response()->json(status: 201);
    }

    public function attachment(WorkItemAttachmentRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->workItems->addAttachment($this->user($request), $workItem, $request->file('file'));

        return response()->json(status: 201);
    }

    public function transition(WorkItemTransitionRequest $request, WorkItem $workItem): JsonResponse
    {
        $item = $this->workItems->transition($this->user($request), $workItem, $request->string('status')->toString());

        return response()->json($this->workItems->present($item));
    }

    public function convert(WorkItemConvertRequest $request, WorkItem $workItem): JsonResponse
    {
        $item = $this->workItems->convert($this->user($request), $workItem, $request->string('kind')->toString());

        return response()->json($this->workItems->present($item));
    }

    public function assignees(WorkItemAssigneesRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->workItems->replaceAssignees($this->user($request), $workItem, $request->validated('assignees'));

        return response()->json($this->workItems->present($workItem));
    }

    public function timeEntry(WorkItemTimeEntryRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->workItems->addTimeEntry($this->user($request), $workItem, $request->validated());

        return response()->json($this->workItems->present($workItem), 201);
    }

    public function budgetEntry(WorkItemBudgetEntryRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->workItems->addBudgetEntry($this->user($request), $workItem, $request->validated());

        return response()->json($this->workItems->present($workItem), 201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
