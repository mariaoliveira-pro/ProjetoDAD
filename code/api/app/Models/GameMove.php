<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameMove extends Model
{
    use HasFactory;

    protected $table = 'game_moves'; // Nome da tabela na BD

    protected $fillable = [
        'game_id',
        'round_number',
        'player_card',
        'bot_card',
        'winner',
        'points_earned'
    ];
}