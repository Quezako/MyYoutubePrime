<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $channel_playlist_id
 * @property string     $date_checked
 * @property string     $date_published
 * @property string     $duration
 * @property string     $my_playlist_id
 * @property int        $status
 * @property string     $title
 */
class Videos extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'videos';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'channel_playlist_id', 'date_checked', 'date_published', 'duration', 'my_playlist_id', 'status', 'title'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'channel_playlist_id' => 'string', 'date_checked' => 'string', 'date_published' => 'string', 'duration' => 'string', 'my_playlist_id' => 'string', 'status' => 'int', 'title' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    // Scopes...

    // Functions ...

    // Relations ...
}
