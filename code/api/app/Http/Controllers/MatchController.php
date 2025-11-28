<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    // POST /api/matches/end
    public function endMatch(Request $request)
    {
        $email = $request->input('email');
        $result = $request->input('result'); // 'win' ou 'loss'
        $score = $request->input('score');   // Pontos da Bisca (0 a 120)
        $duration = $request->input('duration');

        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['error' => 'User not found'], 404);

        $coinsReward = 10; // Prémio base por jogar
        $message = "Jogo terminado.";

        if ($result === 'win') {
            $coinsReward = 50; // Prémio por vitória

            // --- REGRA DO CAPOTE E BANDEIRA ---
            // Bandeira: 120 pontos (Vitória limpa)
            if ($score == 120) {
                $user->bandeira_count += 1;
                $coinsReward += 100; // Bónus grande!
                $message = "VITÓRIA BANDEIRA! (+100 moedas)";
            }
            // Capote: 91 a 119 pontos
            else if ($score >= 91) {
                $user->capote_count += 1;
                $coinsReward += 50; // Bónus médio
                $message = "VITÓRIA CAPOTE! (+50 moedas)";
            }
        }

        try {
            DB::beginTransaction();

            // 1. Atualizar User (Moedas e Achievements)
            $user->coins += $coinsReward;
            $user->save();

            // 2. Guardar no Histórico
            DB::table('match_history')->insert([
                'user_email' => $email,
                'result' => $result,
                'coins_earned' => $coinsReward,
                'duration' => $duration,
                'match_date' => now() // Timestamp atual
            ]);

            DB::commit();

            return response()->json([
                'message' => $message,
                'new_coins' => $user->coins,
                'coins_earned' => $coinsReward,
                'achievements' => [
                    'capote' => ($score >= 91 && $score < 120),
                    'bandeira' => ($score == 120)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erro ao guardar jogo'], 500);
        }
    }
}
