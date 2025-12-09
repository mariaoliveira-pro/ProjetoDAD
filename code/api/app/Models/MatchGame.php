<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchGame extends Model
{
    use HasFactory;

    // Como o nome do model (MatchGame) é diferente da tabela, definimos aqui:
    protected $table = 'matches';

    // Campos que podem ser preenchidos via create() ou update()
    protected $fillable = [
        'type',
        'player1_user_id',
        'player2_user_id',
        'winner_user_id',
        'loser_user_id',
        'status',
        'coins_earned',
        'stake',
        'began_at',
        'ended_at',
        'total_time',
        'player1_marks',
        'player2_marks',
        'player1_points',
        'player2_points',
        'coins_reward',
        'custom'
    ];

    // Converte automaticamente os tipos de dados ao ler/gravar na BD
    protected $casts = [
        'began_at' => 'datetime',
        'ended_at' => 'datetime',
        'custom'   => 'array', // Converte o JSON da BD para Array no PHP automaticamente
        'total_time' => 'decimal:2'
    ];

    // Relações (Opcional, mas dá muito jeito)
    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_user_id');
    }

    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_user_id');
    }

    public function games()
    {
        return $this->hasMany(Game::class, 'match_id');
    }
}
