<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forms\CreateForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreFormRequest;
use App\Http\Resources\Api\V1\FormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FormController extends Controller
{
    public function mine(Request $request): AnonymousResourceCollection
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        // Dashboard filter only: other form queries remain independent of authorship.
        $forms = $request->user()->forms()
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(12);

        return FormResource::collection($forms);
    }

    public function store(StoreFormRequest $request, CreateForm $createForm): JsonResponse
    {
        // The session determines authorship; request fields cannot choose a creator.
        $form = $createForm->handle(
            $request->user(),
            $request->validated('title'),
            $request->validated('description'),
            $request->validated('questions', []),
        );

        return (new FormResource($form))->response()->setStatusCode(201);
    }
}
