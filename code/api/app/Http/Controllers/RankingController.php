<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class RankingController extends Controller
{
    public function globalRanking()
    {
        // 1. TOP 10 WINS (Vitórias)
        $topWins = User::withCount('gamesWon as wins')
            ->orderByDesc('wins')
            ->take(10)
            ->get(['id', 'name', 'nickname', 'avatar']);

        // 2. TOP 10 COINS (Moedas)
        $topCoins = User::select(['id', 'name', 'nickname', 'avatar', 'coins']) // ou 'coins_balance'
            ->orderByDesc('coins')
            ->take(10)
            ->get();

        // 3. TOP 10 ACHIEVEMENTS (Soma de Capote + Bandeira)
        // A Lógica de Ouro está aqui:
        $topAchievements = User::select(['id', 'name', 'nickname', 'avatar', 'capote_count', 'bandeira_count'])
            // Cria uma coluna virtual 'total' somando as duas
            // COALESCE garante que se for null conta como 0
            ->selectRaw('(COALESCE(capote_count, 0) + COALESCE(bandeira_count, 0)) as total_achievements')
            // Ordena por essa coluna virtual
            ->orderByDesc('total_achievements')
            // Critério de desempate: quem tem mais bandeiras ganha
            ->orderByDesc('bandeira_count')
            ->take(10)
            ->get();

        return response()->json([
            'wins'         => $topWins,
            'coins'        => $topCoins,
            'achievements' => $topAchievements,
        ]);
    }
}
