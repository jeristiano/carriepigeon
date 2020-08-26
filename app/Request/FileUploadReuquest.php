<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

/**
 * Class FileUploadReuquest
 * @package App\Request
 */
class FileUploadReuquest extends FormRequest
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
            'file' => "max:10240"
        ];
    }
}