<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $my_channel_id
 * @property string     $name
 * @property int        $sort
 * @property int        $status
 */
class MyPlaylists extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'my_playlists';

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
        'my_channel_id', 'name', 'sort', 'status'
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
        'my_channel_id' => 'string', 'name' => 'string', 'sort' => 'int', 'status' => 'int'
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
