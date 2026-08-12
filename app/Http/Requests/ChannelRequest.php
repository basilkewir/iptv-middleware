<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channelId = $this->route('channel')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:channels,slug,' . $channelId],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'url', 'max:500'],
            'stream_url' => ['required', 'url', 'max:1000'],
            'stream_type' => ['required', 'in:mpegts,hls,dash,rtmp'],
            'category_id' => ['required', 'exists:categories,id'],
            'is_active' => ['boolean'],
            'is_free' => ['boolean'],
            'epg_id' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:2'],
            'language' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
