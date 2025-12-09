<?php
namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\MatchGame;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'has_bandeira' => 'required|boolean', // <-- Obrigatório agora
            'moves'        => 'nullable|array',
        ]);

        // 2. Buscar Jogo
        $match = \App\Models\MatchGame::find($request->match_id);
        if (! $match) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($match->status === 'Ended') {
            return response()->json(['message' => 'Finished'], 200);
        }

        // 3. Preparar Variáveis
        $winnerId   = null;
        $loserId    = null;
        $coinsTotal = 0;

        // Flags para a DB
        $updateCapoteCount   = false;
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
            } elseif ($request->boolean('has_capote')) {
                $coinsTotal += 20; // Bónus Capote
                $updateCapoteCount = true;
            }

        } else {
            // Bot ganhou
            $winnerId = $match->player2_user_id;
            $loserId  = $match->player1_user_id;
            $p1Marks  = $match->player1_marks;
            $p2Marks  = 4;
        }

        try {
            DB::transaction(function () use ($match, $request, $winnerId, $loserId, $p1Marks, $p2Marks, $coinsTotal, $updateCapoteCount, $updateBandeiraCount) {

                // A. Update Match
                DB::table('matches')->where('id', $match->id)->update([
                    'status'         => 'Ended',
                    'winner_user_id' => $winnerId,
                    'loser_user_id'  => $loserId,
                    'ended_at'       => now(),
                    'total_time'     => $request->duration,
                    'player1_marks'  => $p1Marks,
                    'player2_marks'  => $p2Marks,
                    'player1_points' => $request->score,
                    'coins_reward'   => $coinsTotal,
                ]);

                // B. Update User & Transactions (Se o humano ganhou)
                if ($coinsTotal > 0 && $winnerId == $match->player1_user_id) {

                    // Transação
                    DB::table('coin_transactions')->insert([
                        'user_id'                  => $winnerId,
                        'match_id'                 => $match->id,
                        'coin_transaction_type_id' => 3, // Win Reward
                        'coins'                    => $coinsTotal,
                        'transaction_datetime'     => now(),
                    ]);

                    // Saldo e Counts
                    $userQuery = DB::table('users')->where('id', $winnerId);
                    $userQuery->increment('coins_balance', $coinsTotal);

                    if ($updateCapoteCount) {
                        $userQuery->increment('capote_count');
                    }

                    if ($updateBandeiraCount) {
                        $userQuery->increment('bandeira_count');
                    }

                }
            });

            return response()->json([
                'message'      => 'Match saved with history',
                'coins_earned' => $coinsTotal,
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EndMatch Error: " . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function startMatch(Request $request)
    {
        $email = $request->input('email');
        // 1. Autenticar o usuário
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // --- PROCURA O BOT ---
        $bot = User::where('email', "bot@mail.pt")->first();

        // Se o bot não existir, cria um para não dar erro 500
        if (! $bot) {
            $bot = User::create([
                'name'          => 'Bot Jamal',
                'email'         => 'bot@mail.pt',
                'password'      => bcrypt('botpass'),
                'type'          => 'P',
                'coins_balance' => 0,
            ]);
        }

        $entryFee = 10; // Custo de entrada fixo [cite: 32]

        // 2. VERIFICAR SALDO
        if ($user->coins_balance < $entryFee) {
            // Cenário de Insufficient Coins [cite: 34]
            return response()->json([
                'message' => 'Insufficient coins',
                'error'   => true,
            ], 403);
        }

        // 3. DEDUZIR A TAXA DE ENTRADA (Transação Atómica)
        try {
            DB::beginTransaction();

            $user->coins_balance -= $entryFee;
            $user->save();

            // Criar partida
            $matchId = DB::table('matches')->insertGetId([
                'type'            => '9',
                'player1_user_id' => $user->id,

                // AGORA ISTO JÁ NÃO FALHA
                'player2_user_id' => $bot->id,

                'status'          => 'Playing',
                'stake'           => $entryFee,
                'began_at'        => now(),
                'player1_marks'   => 0,
                'player2_marks'   => 0,
                'player1_points'  => 0,
                'player2_points'  => 0,
            ]);

            DB::commit();

            return response()->json([
                'message'     => 'Match started successfully',
                'new_balance' => $user->coins_balance,
                'match_id'    => $matchId,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            // Log do erro real para saberes o que se passa
            \Illuminate\Support\Facades\Log::error("StartMatch Error: " . $e->getMessage());
            return response()->json(['message' => 'Erro interno: ' . $e->getMessage()], 500);
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

    // GET /api/matches/user
    public function userMatches(Request $request)
    {
        // Usa o utilizador autenticado (token / Sanctum)
        $userId = $request->user()->id;

        // Vai buscar as matches onde o player participou
        // e conta quantos games existem em cada match
        $matches = MatchGame::withCount('games')
            ->where(function ($q) use ($userId) {
                $q->where('player1_user_id', $userId)
                    ->orWhere('player2_user_id', $userId);
            })
            ->orderBy('began_at', 'desc')
            ->get();

        // Se não houver matches, devolve lista vazia
        if ($matches->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma partida encontrada.',
                'data'    => [],
            ], 200);
        }

        return response()->json([
            'data' => $matches,
        ], 200);
    }

    // GET /api/matches/{id}/games
    public function matchGames($matchId)
    {
        // Verifica se a match existe
        $match = MatchGame::find($matchId);

        if (! $match) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        // Buscar os games desta match APENAS PELO CAMPOS match_id (simples)
        $games = Game::where('match_id', $matchId)
            ->orderBy('began_at', 'asc')
            ->get();

        return response()->json([
            'match' => $match,
            'games' => $games,
        ], 200);
    }

    // POST /api/matches/undo
    public function undoPlay(Request $request)
    {
        // 1. Validar inputs
        $request->validate([
            'email'    => 'required|email',
            'match_id' => 'required|integer|exists:matches,id', // Garante que o match existe na BD
            'cost'     => 'required|integer|in:5,10,15',        // Segurança: Só aceita os valores da regra (5, 10, 15)
        ]);

        // 2. Buscar Utilizador
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 3. Verificar Saldo
        if ($user->coins_balance < $request->cost) {
            return response()->json([
                'message'         => 'Saldo insuficiente',
                'current_balance' => $user->coins_balance,
            ], 403);
        }

        try {
            // 4. Executar Transação Atómica
            DB::transaction(function () use ($user, $request) {

                // A. Debitar moedas do utilizador
                // O método decrement é mais atómico e seguro
                $user->decrement('coins_balance', $request->cost);

                // B. Registar na tabela de transações
                // Assumimos que o TYPE ID 4 é para "Undo/Retirar Carta" (Ajusta se for outro ID na tua BD)
                DB::table('coin_transactions')->insert([
                    'user_id'                  => $user->id,
                    'match_id'                 => $request->match_id,
                    'coin_transaction_type_id' => 4,
                    'coins'                    => -($request->cost), // Garante que fica negativo na BD
                    'transaction_datetime'     => now(),
                    'custom'                   => json_encode([
                        'type'        => 'undo_play',
                        'cost'        => $request->cost,
                        'description' => 'Player retried a move',
                    ]),
                ]);
            });

            // 5. Retornar sucesso e o novo saldo atualizado
            return response()->json([
                'message'     => 'Undo successful',
                'new_balance' => $user->fresh()->coins_balance, // fresh() garante que pega o valor atualizado da BD
            ], 200);

        } catch (\Exception $e) {
            // Log do erro para debug
            \Illuminate\Support\Facades\Log::error("UndoPlay Error: " . $e->getMessage());

            return response()->json(['error' => 'Server error processing undo'], 500);
        }
    }

}
