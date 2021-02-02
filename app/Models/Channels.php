<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $date_checked
 * @property string     $date_last_upload
 * @property string     $my_channel_id
 * @property string     $name
 * @property string     $playlist_id
 * @property int        $sort
 * @property int        $status
 */
class Channels extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'channels';

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
        'date_checked', 'date_last_upload', 'my_channel_id', 'name', 'playlist_id', 'sort', 'status'
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
        'date_checked' => 'int', 'date_last_upload' => 'string', 'my_channel_id' => 'string', 'name' => 'string', 'playlist_id' => 'string', 'sort' => 'int', 'status' => 'int'
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
