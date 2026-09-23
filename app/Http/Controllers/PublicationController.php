<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicationAttachmentRequest;
use App\Http\Requests\StoreInfoboardRequest;
use App\Http\Requests\StoreNewsRequest;
use App\Http\Requests\UpdateInfoboardRequest;
use App\Http\Requests\UpdateNewsRequest;
use App\Models\Infoboard;
use App\Models\News;
use App\Models\User;
use App\Services\PublicationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicationController extends Controller
{
    public function __construct(private readonly PublicationService $publications) {}

    public function newsIndex(Request $request): JsonResponse { return $this->index($request, 'news'); }
    public function infoboardIndex(Request $request): JsonResponse { return $this->index($request, 'infoboard'); }
    public function newsShow(Request $request, News $news): JsonResponse { return $this->show($request, $news, 'news'); }
    public function infoboardShow(Request $request, Infoboard $infoboard): JsonResponse { return $this->show($request, $infoboard, 'infoboard'); }

    public function newsStore(StoreNewsRequest $request): JsonResponse { return $this->store($request, 'news'); }
    public function infoboardStore(StoreInfoboardRequest $request): JsonResponse { return $this->store($request, 'infoboard'); }
    public function newsUpdate(UpdateNewsRequest $request, News $news): JsonResponse { return $this->update($request, $news, 'news'); }
    public function infoboardUpdate(UpdateInfoboardRequest $request, Infoboard $infoboard): JsonResponse { return $this->update($request, $infoboard, 'infoboard'); }
    public function newsPublish(Request $request, News $news): JsonResponse { return $this->publish($request, $news, 'news'); }
    public function infoboardPublish(Request $request, Infoboard $infoboard): JsonResponse { return $this->publish($request, $infoboard, 'infoboard'); }
    public function newsUnpublish(Request $request, News $news): JsonResponse { return $this->unpublish($request, $news, 'news'); }
    public function infoboardUnpublish(Request $request, Infoboard $infoboard): JsonResponse { return $this->unpublish($request, $infoboard, 'infoboard'); }
    public function newsAttachment(PublicationAttachmentRequest $request, News $news): JsonResponse { return $this->attachment($request, $news, 'news'); }
    public function infoboardAttachment(PublicationAttachmentRequest $request, Infoboard $infoboard): JsonResponse { return $this->attachment($request, $infoboard, 'infoboard'); }

    private function index(Request $request, string $type): JsonResponse
    {
        return response()->json($this->publications->index($this->user($request), $type));
    }

    private function show(Request $request, Model $publication, string $type): JsonResponse
    {
        return response()->json($this->publications->show($this->user($request), $publication, $type));
    }

    private function store(Request $request, string $type): JsonResponse
    {
        $publication = $this->publications->create($this->user($request), $type, $request->validated());

        return response()->json($this->publications->present($publication, $type), 201);
    }

    private function update(Request $request, Model $publication, string $type): JsonResponse
    {
        $publication = $this->publications->update($this->user($request), $publication, $type, $request->validated());

        return response()->json($this->publications->present($publication, $type));
    }

    private function publish(Request $request, Model $publication, string $type): JsonResponse
    {
        return response()->json($this->publications->present($this->publications->publish($this->user($request), $publication, $type), $type));
    }

    private function unpublish(Request $request, Model $publication, string $type): JsonResponse
    {
        return response()->json($this->publications->present($this->publications->unpublish($this->user($request), $publication, $type), $type));
    }

    private function attachment(PublicationAttachmentRequest $request, Model $publication, string $type): JsonResponse
    {
        $this->publications->attach($this->user($request), $publication, $type, $request->file('file'));

        return response()->json($this->publications->present($publication, $type), 201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
