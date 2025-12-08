<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'status',
        'player1_user_id',
        'player2_user_id',
        'winner_user_id',
        'loser_user_id',
        'is_draw',
        'match_id',
        'began_at',
        'ended_at',
        'total_time',
        'player1_points',
        'player2_points'
    ];

    public function winner()
    {
        return $this->belongsTo(User::class, "winner_user_id");
    }
}
