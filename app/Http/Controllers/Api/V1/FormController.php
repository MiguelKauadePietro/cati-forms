<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forms\CreateForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreFormRequest;
use App\Http\Resources\Api\V1\FormResource;
use Illuminate\Http\JsonResponse;

class FormController extends Controller
{
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
