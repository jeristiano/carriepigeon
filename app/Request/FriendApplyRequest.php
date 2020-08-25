<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class FriendApplyRequest extends FormRequest
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
            'receiver_id' => 'required|numeric',
            'friend_group_id' => 'required|numeric',
            'application_reason' => 'required|string|max:255',
        ];
    }
}
