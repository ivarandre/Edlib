<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class H5PResult extends Model
{
    public $timestamps = false;

    protected $table = 'h5p_results';

    protected $fillable = [
        'content_id',
        'user_id',
        'score',
        'max_score',
        'opened',
        'finished',
        'time',
        'context'
    ];
}
