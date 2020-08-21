<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

/**
 * Class RegisterRequest
 * @package App\Request
 */
class RegisterRequest extends FormRequest
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
            'email' => 'required|email|max:255|unique:user,email',
            'password' => 'required|string|max:50|min:6',
        ];
    }
}
