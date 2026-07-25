<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'brand_name' => ['required', 'string', 'max:100'],
            'segment' => ['required', 'string'],
            'website' => ['nullable', 'url'],
            'description' => ['nullable', 'string', 'max:1000'],
            'competitors' => ['array', 'max:5'],
            'competitors.*' => ['url'],
            'channels' => ['array', 'min:1'],
            'channels.*' => ['in:instagram,tiktok,youtube,linkedin'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brand_name.required' => 'Informe o nome da marca.',
            'brand_name.max' => 'O nome da marca deve ter no máximo 100 caracteres.',
            'segment.required' => 'Informe o segmento da marca.',
            'website.url' => 'O website deve ser uma URL válida.',
            'description.max' => 'A descrição deve ter no máximo 1000 caracteres.',
            'competitors.array' => 'Os concorrentes devem ser uma lista.',
            'competitors.max' => 'Informe no máximo 5 concorrentes.',
            'competitors.*.url' => 'Cada concorrente deve ser uma URL válida.',
            'channels.array' => 'Os canais devem ser uma lista.',
            'channels.min' => 'Informe ao menos um canal.',
            'channels.*.in' => 'Canal inválido. Utilize instagram, tiktok, youtube ou linkedin.',
        ];
    }
}
