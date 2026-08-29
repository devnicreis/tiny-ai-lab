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
            'brand_name.required' => 'Please provide the brand name.',
            'brand_name.max' => 'The brand name must not exceed 100 characters.',
            'segment.required' => 'Please provide the brand segment.',
            'website.url' => 'The website must be a valid URL.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'competitors.array' => 'Competitors must be a list.',
            'competitors.max' => 'Please provide at most 5 competitors.',
            'competitors.*.url' => 'Each competitor must be a valid URL.',
            'channels.array' => 'Channels must be a list.',
            'channels.min' => 'Please provide at least one channel.',
            'channels.*.in' => 'Invalid channel. Use instagram, tiktok, youtube, or linkedin.',
        ];
    }
}
