<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function personal(Request $request)
    {
        $user = $request->user(); // user autenticado pelo token

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // --------- ESTATÍSTICAS ---------

        // Número total de jogos onde o user participou
        $totalMatches = Game::where('player1_user_id', $user->id)
            ->orWhere('player2_user_id', $user->id)
            ->count();

        // Número de vitórias
        $wins = Game::where('winner_user_id', $user->id)->count();

        // Winrate
        $winrate = $totalMatches > 0
            ? round(($wins / $totalMatches) * 100, 1)
            : 0;

        // Coins ganhas (somando coins_balance do winner)
        $coinsEarned = Game::where('winner_user_id', $user->id)
            ->with('winner')
            ->get()
            ->sum(fn($g) => $g->winner->coins_balance ?? 0);


        return response()->json([
            'total_matches' => $totalMatches,
            'wins' => $wins,
            'winrate' => $winrate,
            'coins_earned' => $coinsEarned,
            'capote_count' => $user->capote_count ?? 0, //diretamente do User model
            'bandeira_count' => $user->bandeira_count ?? 0
        ]);
    }


}
