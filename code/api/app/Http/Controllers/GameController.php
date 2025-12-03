<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'email' => 'required|email',
            'player1_points' => 'required|integer', // Os teus pontos
            'player2_points' => 'required|integer', // Pontos do Bot
            'duration' => 'required|integer',       // Duração em segundos
            'match_id' => 'nullable|exists:matches,id' // Opcional: se fizer parte de um Match
        ]);

        // 2. Identificar o Utilizador e o Bot
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $botUser = User::where('email', "bot@bisca.pt")->first();
    
        if (!$botUser) {
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
            $isDraw = 1; // Empate
        }

        // 4. Criar o Registo na Tabela
        try {
            $game = Game::create([
                'type' => '9', // Bisca de 3 (Padrão)
                'status' => 'Ended', // O jogo já acabou
                'player1_user_id' => $user->id,
                'player2_user_id' => $botId,
                'match_id' => $request->match_id, // Se não enviares nada, fica NULL
                'winner_user_id' => $winnerId,
                'loser_user_id' => $loserId,
                'is_draw' => $isDraw,
                'player1_points' => $p1Score,
                'player2_points' => $p2Score,
                'total_time' => $request->duration,
                'ended_at' => now(),
                'began_at' => now()->subSeconds($request->duration) // Calcula o início baseado na duração
            ]);

            return response()->json([
                'message' => 'Game saved successfully',
                'game_id' => $game->id
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao guardar jogo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Game $game)
    {
        //
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
