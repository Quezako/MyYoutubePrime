<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $channel_playlist_id
 * @property string $my_playlist_id
 * @property string $title
 * @property string $date_checked
 * @property string $date_published
 * @property string $duration
 * @property int $status
 * @property MyPlaylist $myPlaylist
 * @property Channel $channel
 */
class Video extends Model
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
    protected $fillable = ['channel_playlist_id', 'my_playlist_id', 'title', 'date_checked', 'date_published', 'duration', 'status'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function myPlaylist()
    {
        return $this->belongsTo('App\MyPlaylist');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function channel()
    {
        return $this->belongsTo('App\Channel', 'channel_playlist_id', 'playlist_id');
    }
}
