<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;

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
        //
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
