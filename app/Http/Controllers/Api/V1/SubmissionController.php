<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubmissionResource;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubmissionController extends Controller
{
    public function index(Request $request, Form $form): AnonymousResourceCollection
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        // RN10: every authenticated user can read responses; always scope to the form.
        return SubmissionResource::collection($form->submissions()
            ->with(['answers' => fn ($query) => $query->orderBy('id'), 'answers.options' => fn ($query) => $query->orderBy('position')])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(12));
    }
}
