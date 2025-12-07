<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MatchGame;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MatchController extends Controller
{
    // POST /api/matches/end
    public function endMatch(Request $request)
    {
        // 1. Validar inputs
        $request->validate([
            'match_id'     => 'required|integer',
            'result'       => 'required|in:win,loss',
            'duration'     => 'required|numeric',
            'score'        => 'nullable|integer',
            'has_capote'   => 'required|boolean', // <-- Obrigatório agora
            'has_bandeira' => 'required|boolean'  // <-- Obrigatório agora
        ]);

        // 2. Buscar Jogo
        $match = \App\Models\MatchGame::find($request->match_id);
        if (!$match) return response()->json(['message' => 'Not found'], 404);
        if ($match->status === 'Ended') return response()->json(['message' => 'Finished'], 200);

        // 3. Preparar Variáveis
        $winnerId = null; 
        $loserId = null;
        $coinsTotal = 0;
        
        // Flags para a DB
        $updateCapoteCount = false;
        $updateBandeiraCount = false;

        if ($request->result === 'win') {
            $winnerId = $match->player1_user_id;
            $loserId  = $match->player2_user_id;
            
            $p1Marks = 4;
            $p2Marks = $match->player2_marks;

            // --- LÓGICA DE RECOMPENSAS (Agora baseada nos teus booleanos) ---
            $coinsTotal = 50; // Base Reward

            if ($request->boolean('has_bandeira')) {
                $coinsTotal += 30; // Bónus Bandeira
                $updateBandeiraCount = true;
            } 
            elseif ($request->boolean('has_capote')) {
                $coinsTotal += 20; // Bónus Capote
                $updateCapoteCount = true;
            }

        } else {
            // Bot ganhou
            $winnerId = $match->player2_user_id;
            $loserId  = $match->player1_user_id;
            $p1Marks = $match->player1_marks;
            $p2Marks = 4;
        }

        try {
            DB::transaction(function () use ($match, $request, $winnerId, $loserId, $p1Marks, $p2Marks, $coinsTotal, $updateCapoteCount, $updateBandeiraCount) {

                // A. Update Match
                DB::table('matches')->where('id', $match->id)->update([
                    'status' => 'Ended',
                    'winner_user_id' => $winnerId,
                    'loser_user_id' => $loserId,
                    'ended_at' => now(),
                    'total_time' => $request->duration,
                    'player1_marks' => $p1Marks,
                    'player2_marks' => $p2Marks,
                    'player1_points' => $request->score
                ]);

                // B. Update User & Transactions (Se o humano ganhou)
                if ($coinsTotal > 0 && $winnerId == $match->player1_user_id) {
                    
                    // Transação
                    DB::table('coin_transactions')->insert([
                        'user_id' => $winnerId,
                        'match_id' => $match->id,
                        'coin_transaction_type_id' => 3, // Win Reward
                        'coins' => $coinsTotal,
                        'transaction_datetime' => now()
                    ]);

                    // Saldo e Counts
                    $userQuery = DB::table('users')->where('id', $winnerId);
                    $userQuery->increment('coins_balance', $coinsTotal);
                    
                    if ($updateCapoteCount) $userQuery->increment('capote_count');
                    if ($updateBandeiraCount) $userQuery->increment('bandeira_count');
                }
            });

            return response()->json([
                'message' => 'Match saved',
                'coins_earned' => $coinsTotal
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EndMatch Error: ".$e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
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

            $bot = User::where('email', "bot@mail.pt")->first();
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
