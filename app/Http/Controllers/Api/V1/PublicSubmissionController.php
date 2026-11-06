<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Submissions\CreateSubmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\StoreSubmissionRequest;
use App\Models\Form;
use Illuminate\Http\JsonResponse;

class PublicSubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request, Form $form, CreateSubmission $createSubmission): JsonResponse
    {
        $createSubmission->handle(
            $form,
            $request->validated('answers'),
            $request->validated('contact_email'),
        );

        return response()->json(['message' => 'Resposta enviada com sucesso.'], 201);
    }
}
