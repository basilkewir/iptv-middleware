<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [

            'value' => 'string',
        
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value, ?string $group = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            array_merge(['value' => $value], $group ? ['group' => $group] : [])
        );
    }

    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }
}
