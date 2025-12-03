<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MatchController extends Controller
{
    // POST /api/matches/end
    public function endMatch(Request $request)
    {
        // 1. Validar inputs
        $request->validate([
            'email' => 'required|email',
            'match_id' => 'required|exists:matches,id', // OBRIGATÓRIO: saber qual match fechar
            'result' => 'required|in:win,loss',         // Quem ganhou?
            'duration' => 'required|integer',            // Tempo total
            'score' => 'integer'                         // Pontos finais (para bónus extra)
        ]);

        // 2. Buscar Utilizador e Bot
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $bot = User::where('email', "bot@bisca.pt")->first();
        if (!$bot) {
            return response()->json(['error' => 'Bot not found'], 500);
        }

        // 3. Buscar o Match existente
        $match = \App\Models\MatchGame::find($request->match_id); 
        // Nota: Se o teu model se chama 'Match', cuidado porque 'match' é palavra reservada no PHP 8.
        // O ideal é o model chamar-se 'MatchGame' ou usares o namespace completo.

        if ($match->status === 'Ended') {
            return response()->json(['message' => 'Match already finished'], 200);
        }

        // 4. Definir Vencedor e Perdedor
        $winnerId = null;
        $loserId = null;
        $coinsReward = 10; // Prémio de consolação base

        if ($request->result === 'win') {
            // Humano Ganhou
            $winnerId = $user->id;
            $loserId = $bot->id;
            
            $coinsReward = 50; // Prémio Base Vitória
            
            // Bónus (Capote/Bandeira baseados no score do último jogo, se aplicável)
            $score = $request->input('score', 0);
            if ($score == 120) {
                $coinsReward += 100; // Bandeira
            } elseif ($score >= 91) {
                $coinsReward += 50;  // Capote
            }

            // Atualizar marcas finais (assumindo que vitória = 4 marcas)
            $p1Marks = 4; 
            $p2Marks = $match->player2_marks; // Mantém as que o bot tinha

        } else {
            // Bot Ganhou
            $winnerId = $bot->id;
            $loserId = $user->id;
            
            $p1Marks = $match->player1_marks; // Mantém as tuas
            $p2Marks = 4; // Bot chegou a 4
        }

        // 5. Transação na Base de Dados (Para garantir que tudo atualiza ou nada atualiza)
        try {
            DB::beginTransaction();

            // 5.1 Atualizar a Tabela matches
            $match->update([
                'status' => 'Ended', // ou 'Finished', confirma o teu ENUM na BD
                'winner_user_id' => $winnerId,
                'loser_user_id' => $loserId,
                'ended_at' => now(),
                'total_time' => $request->duration,
                'player1_marks' => $p1Marks,
                'player2_marks' => $p2Marks,
                // 'player1_points' => $score // Opcional: guardar pontos do último jogo
            ]);

            // 5.2 Dar as moedas ao Utilizador
            $user->coins += $coinsReward;
            
            // Atualizar estatísticas do user (opcional)
            if ($request->result === 'win') {
                // $user->wins += 1; // Se tiveres este campo
            }

            $user->save();

            DB::commit();

            return response()->json([
                'message' => 'Match finished successfully',
                'match_id' => $match->id,
                'new_balance' => $user->coins,
                'coins_earned' => $coinsReward,
                'result' => $request->result
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erro ao atualizar match: ' . $e->getMessage()], 500);
        }
    }

    public function startMatch(Request $request)
    {
        $email = $request->input('email');
        // 1. Autenticar o usuário
        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['error' => 'User not found'], 404);

        $entryFee = 10; // Custo de entrada fixo [cite: 32]
        
        // 2. VERIFICAR SALDO
        if ($user->coins_balance < $entryFee) {
            // Cenário de Insufficient Coins [cite: 34]
            return response()->json([
                'message' => 'Insufficient coins',
                'error' => true
            ], 403); 
        }

        // 3. DEDUZIR A TAXA DE ENTRADA (Transação Atómica)
        try {
            DB::beginTransaction(); // Garante que a dedução e a criação do match sejam seguras
            
            $user->coins_balance -= $entryFee;
            $user->save();

            $bot = User::where('email', "bot@bisca.pt")->first();
            // [TODO: Criar o registro da partida na base de dados]
            $matchId = DB::table('matches')->insertGetId([
                'type' => '9', // Bisca de 9 cartas [cite: 7, 77]
                'player1_user_id' => $user->id,
                // O bot é o player 2. Assumimos um ID de bot conhecido ou 0
                'player2_user_id' => $bot->id, // Ex: ID 0 para o bot [cite: 9]
                'status' => 'Playing',
                'stake' => $entryFee,
                'began_at' => now(), // Timestamp atual
                'player1_marks' => 0,
                'player2_marks' => 0,
                'player1_points' => 0,
                'player2_points' => 0,
                // 'total_time', 'winner_user_id', 'loser_user_id' serão atualizados no fim
            ]);

            DB::commit();

            // 4. Sucesso: Retorna o Game Board (ou os dados necessários para o cliente iniciar o jogo)
            return response()->json([
                'message' => 'Match started successfully',
                'new_balance' => $user->coins_balance,
                'match_id' => $matchId, // ID da partida criada
                
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro interno na transação.'], 500);
        }
    }

    public function index(Request $request)
    {
        // 1. Busca todos os registros da tabela 'matches'
        $matches = DB::table('matches')
                    ->orderBy('began_at', 'desc') // Ordenar do mais recente para o mais antigo
                    ->get();

        // 2. Retorna a coleção de matches
        if ($matches->isEmpty()) {
            return response()->json(['message' => 'Nenhuma partida encontrada.', 'matches' => []], 200);
        }

        return response()->json(['matches' => $matches], 200);
    }


}
