<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepositoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage-repositories');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'github_repo_id' => 'required|integer|min:1',
            'installation_id' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'github_repo_id.required' => 'O ID do repositório no GitHub é obrigatório.',
            'github_repo_id.integer' => 'O ID do repositório deve ser um número inteiro.',
        ];
    }
}