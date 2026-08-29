<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRepositoryRequest extends FormRequest
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
            'is_active' => 'boolean',
            'comment_on_pr' => 'boolean',
            'escalate_on_hostile' => 'boolean',
            'escalation_webhook_url' => 'nullable|url|max:500',
            'security_level' => 'in:standard,elevated,critical',
            'installation_id' => 'nullable|integer|min:1',
        ];
    }
}
