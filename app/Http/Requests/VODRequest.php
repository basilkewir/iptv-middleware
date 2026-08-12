<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VODRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vodId = $this->route('vod')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:vods,slug,' . $vodId],
            'description' => ['nullable', 'string', 'max:2000'],
            'poster' => ['nullable', 'url', 'max:500'],
            'backdrop' => ['nullable', 'url', 'max:500'],
            'stream_url' => ['required', 'url', 'max:1000'],
            'category_id' => ['required', 'exists:categories,id'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'is_free' => ['boolean'],
            'is_featured' => ['boolean'],
            'genre' => ['nullable', 'string', 'max:255'],
            'actors' => ['nullable', 'string', 'max:500'],
            'director' => ['nullable', 'string', 'max:255'],
        ];
    }
}
