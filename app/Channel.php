<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $playlist_id
 * @property string $my_channel_id
 * @property string $name
 * @property string $date_last_upload
 * @property int $date_checked
 * @property int $status
 * @property int $sort
 * @property Video[] $videos
 */
class Channel extends Model
{
    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     * 
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = ['playlist_id', 'my_channel_id', 'name', 'date_last_upload', 'date_checked', 'status', 'sort'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function videos()
    {
        return $this->hasMany('App\Video', 'channel_playlist_id', 'playlist_id');
    }
}
