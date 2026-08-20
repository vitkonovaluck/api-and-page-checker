<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ImportSitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.$this->maxUploadKb(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Оберіть JSON-файл експорту.',
            'file.file' => 'Оберіть JSON-файл експорту.',
            'file.max' => 'Файл занадто великий (макс. '.$this->maxUploadMb().' МБ).',
        ];
    }

    private function maxUploadKb(): int
    {
        return (int) config('checking.transfer.max_upload_kb', 10240);
    }

    private function maxUploadMb(): int
    {
        return (int) ceil($this->maxUploadKb() / 1024);
    }
}
