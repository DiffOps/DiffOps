<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username' => 'sometimes|string|min:2|max:50|alpha_dash|unique:profiles,username,' . auth()->id(),
            'email' => 'sometimes|email|max:255|unique:users,email,' . auth()->id(),
            'preferences.notifications.email' => 'boolean',
            'preferences.notifications.push' => 'boolean',
        ];
    }
}