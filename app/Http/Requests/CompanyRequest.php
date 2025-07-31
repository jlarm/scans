<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function rules()
    {
        return [
            'uuid' => ['required'],
            'name' => ['required'],
        ];
    }

    public function authorize()
    {
        return true;
    }
}
