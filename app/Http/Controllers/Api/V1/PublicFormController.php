<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicFormResource;
use App\Models\Form;

class PublicFormController extends Controller
{
    public function show(Form $form): PublicFormResource
    {
        return new PublicFormResource($form->load('questions.options'));
    }
}
