<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

/**
 * Class UserUpdateRequest
 * @package App\Request
 */
class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize (): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules (): array
    {
        return [
            'username' => 'sometimes|required|string|max:255',
            'avatar' => 'sometimes|required|string',
        ];
    }
}
