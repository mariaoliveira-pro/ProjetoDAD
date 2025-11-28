<?php
namespace App\Http\Controllers;

use App\Models\User;

class RankingController extends Controller
{
    public function globalRanking()
    {
        // TOP 10 WINS
        $topWins = User::withCount('gamesWon as wins')
            ->orderByDesc('wins')
            ->take(10)
            ->get(['id', 'name', 'nickname', 'coins_balance']);

        // TOP 10 COINS
        $topCoins = User::withCount('gamesWon as wins')
            ->orderByDesc('coins_balance')
            ->take(10)
            ->get(['id', 'name', 'nickname', 'coins_balance']);

        // ACHIEVEMENTS (placeholder)
        $topAchievements = User::withCount('gamesWon as wins')
            // Seleciona as colunas normais E cria a coluna virtual 'total_achievements'
            ->select(['id', 'name', 'nickname', 'avatar', 'capote_count', 'bandeira_count'])
            ->selectRaw('(capote_count + bandeira_count) as total_achievements')
            ->orderByDesc('total_achievements')
            ->take(10)
            ->get();

        return response()->json([
            'wins'         => $topWins,
            'coins'        => $topCoins,
            'achievements' => $topAchievements,
        ]);
    }
}
