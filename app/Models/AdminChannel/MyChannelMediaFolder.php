<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MyChannelMediaFolder extends Model
{
    protected $table = 'my_channel_media_folders';

    protected $fillable = [
        'channel_id', 'parent_id', 'name',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'channel_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(MyChannelContent::class, 'folder_id');
    }
}