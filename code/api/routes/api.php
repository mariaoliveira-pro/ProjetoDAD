<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MatchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/me', function (Request $request) {
        return $request->user();
    });
    Route::post('logout', [AuthController::class, 'logout']);
});

Route::apiResource('games', GameController::class);

Route::middleware('auth:sanctum')->get('/stats/personal', [StatsController::class, 'personal']);


Route::middleware('auth:sanctum')->get('/users/me/games', [GameController::class, 'userGames']);
Route::middleware("auth:sanctum")->get("/ranking/global", [RankingController::class, "globalRanking"]);

// Rotas da Loja
Route::get('/shop/items', [ShopController::class, 'index']); // Listar itens
Route::post('/shop/buy', [ShopController::class, 'buy']);    // Comprar item

// Rotas do Inventário (Customizações)
Route::get('/users/inventory', [InventoryController::class, 'index']);

// Rota para Fim de Jogo (Escrever dados e dar moedas)
Route::post('/matches/end', [MatchController::class, 'endMatch']);

Route::post('/matches/start', [MatchController::class, 'startMatch']);

Route::get('/matches', [MatchController::class, 'index']);

Route::post('/users/equip', [InventoryController::class, 'equip']);

Route::post('/matches/undo', [MatchController::class, 'undoPlay']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/matches/user', [MatchController::class, 'userMatches']);
    Route::get('/matches/{id}/games', [MatchController::class, 'matchGames']);
});

