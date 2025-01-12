<?php

namespace App;

use App\Traits\UuidForKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionSetQuestionAnswer extends Model
{
    use HasFactory;
    use UuidForKey;

    public function question()
    {
        return $this->belongsTo(QuestionSetQuestion::class, 'question_id')->ordered();
    }

    public function scopeOrdered($query)
    {
        $query->orderBy('order');
        return $query;
    }
}
