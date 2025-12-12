<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameMove;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Game::query()->with(['winner']);



        if ($request->has('type') && in_array($request->type, ['3', '9'])) {
            $query->where('type', $request->type);
        }

        if ($request->has('status') && in_array($request->status, ['Pending', 'Playing', 'Ended', 'Interrupted'])) {
            $query->where('status', $request->status);
        }



        // Sorting
        $sortField = $request->input('sort_by', 'began_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSortFields = [
            'began_at',
            'ended_at',
            'total_time',
            'type',
            'status'
        ];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }




        // Pagination
        $perPage = $request->input('per_page', 15);
        $games = $query->paginate($perPage);

        return response()->json([
            'data' => $games->items(),
            'meta' => [
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
                'per_page' => $games->perPage(),
                'total' => $games->total()
            ]
        ]);
    }

    public function userGames(Request $request)
    {
        $userId = $request->user()->id;

        $games = Game::with(['winner'])
            ->where(function ($q) use ($userId) {
                $q->where('player1_user_id', $userId)
                    ->orWhere('player2_user_id', $userId);
            })
            ->orderBy('began_at', 'desc')
            ->get();

        return response()->json([
            'data' => $games
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validar os dados que vêm do Android
        $request->validate([
            'email'          => 'required|email',
            'player1_points' => 'required|integer',
            'player2_points' => 'required|integer',
            'duration'       => 'required|integer',
            'match_id'       => 'nullable|exists:matches,id',
            'moves'          => 'nullable|array' // <--- NOVA VALIDAÇÃO PARA AS VAZAS
        ]);

        // 2. Identificar o Utilizador e o Bot
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Verifica se o bot existe (podes usar o email que tens na BD)
        $botUser = User::where('email', "bot@mail.pt")->first(); // ou bot@mail.pt conforme tenhas
        if (!$botUser) {
            // Fallback: Tenta encontrar pelo ID ou cria (opcional, mas evita erros 500)
             return response()->json(['error' => 'Bot configuration missing'], 500);
        }
        $botId = $botUser->id;

        // 3. Calcular Vencedor e Perdedor
        $p1Score = $request->player1_points;
        $p2Score = $request->player2_points;

        $winnerId = null;
        $loserId = null;
        $isDraw = 0;

        if ($p1Score > $p2Score) {
            $winnerId = $user->id;
            $loserId = $botId;
        } elseif ($p2Score > $p1Score) {
            $winnerId = $botId;
            $loserId = $user->id;
        } else {
            $isDraw = 1;
        }

        // 4. Guardar Jogo e Vazas (Transação)
        try {
            // Usamos DB::transaction para garantir que grava Jogo + Vazas ou nada
            $gameId = DB::transaction(function () use ($request, $user, $botId, $winnerId, $loserId, $isDraw, $p1Score, $p2Score) {

                // A. Criar o Registo na Tabela GAMES
                // Nota: Game::create retorna o objeto, guardamos na variável $game
                $game = Game::create([
                    'type'            => '9',
                    'status'          => 'Ended',
                    'player1_user_id' => $user->id,
                    'player2_user_id' => $botId,
                    'match_id'        => $request->match_id,
                    'winner_user_id'  => $winnerId,
                    'loser_user_id'   => $loserId,
                    'is_draw'         => $isDraw,
                    'player1_points'  => $p1Score,
                    'player2_points'  => $p2Score,
                    'total_time'      => $request->duration,
                    'ended_at'        => now(),
                    'began_at'        => now()->subSeconds($request->duration)
                ]);

                // B. Salvar as VAZAS na tabela GAME_MOVES
                if ($request->has('moves') && is_array($request->moves)) {
                    $movesData = [];

                    foreach ($request->moves as $move) {
                        $movesData[] = [
                            'game_id'       => $game->id,        // <--- Liga ao ID do jogo criado em cima
                            'round_number'  => $move['round'],   // Vem do Android
                            'player_card'   => $move['p_card'],  // ex: "ac"
                            'bot_card'      => $move['b_card'],  // ex: "7o"
                            'winner'        => $move['winner'],  // "player" ou "bot"
                            'points_earned' => $move['points'],
                            'created_at'    => now(),
                            'updated_at'    => now()
                        ];
                    }

                    // Inserir todas as vazas de uma vez
                    if (count($movesData) > 0) {
                        DB::table('game_moves')->insert($movesData);
                    }
                }

                return $game->id; // Retorna o ID para usar na resposta
            });

            return response()->json([
                'message' => 'Game saved successfully with history',
                'game_id' => $gameId
            ], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SaveGame Error: " . $e->getMessage());
            return response()->json(['error' => 'Erro ao guardar jogo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     * GET /api/games/{id}
     */
    public function show($id)
    {
        try {
            // 1. Buscar o Jogo
            $game = Game::find($id);

            if (!$game) {
                return response()->json(['message' => 'Game not found'], 404);
            }

            // 2. Buscar as Vazas (Moves) associadas
            // Certifica-te que tens "use App\Models\GameMove;" no topo do ficheiro
            $moves = GameMove::where('game_id', $id)
                        ->orderBy('round_number', 'asc')
                        ->get();

            // 3. Juntar tudo na mesma resposta
            // Converte o jogo em array e adiciona os moves
            $responseData = $game->toArray();
            $responseData['moves'] = $moves;

            return response()->json($responseData, 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro ao mostrar jogo $id: " . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Game $game)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Game $game)
    {
        //
    }
    
}
