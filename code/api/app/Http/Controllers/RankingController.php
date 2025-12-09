<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RankingController extends Controller
{
    public function globalRanking(Request $request)
    {
        return new StreamedResponse(function () {

            echo '{';

            // ================================
            // WINS
            // ================================
            echo '"wins":{"data":[';

            $first = true;

            User::withCount('gamesWon as wins')
                ->orderByDesc('wins')
                ->chunk(500, function ($users) use (&$first) {
                    foreach ($users as $u) {
                        if (!$first) echo ',';
                        $first = false;

                        echo json_encode([
                            'id'    => $u->id,
                            'name'  => $u->name,
                            'nickname' => $u->nickname,
                            'wins'  => $u->wins,
                        ]);
                    }
                });

            echo ']},';

            // ================================
            // COINS
            // ================================
            echo '"coins":{"data":[';

            $first = true;

            User::orderByDesc('coins_balance')
                ->chunk(500, function ($users) use (&$first) {
                    foreach ($users as $u) {
                        if (!$first) echo ',';
                        $first = false;

                        echo json_encode([
                            'id'    => $u->id,
                            'name'  => $u->name,
                            'nickname' => $u->nickname,
                            'coins_balance' => $u->coins_balance,
                        ]);
                    }
                });

            echo ']},';

            // ================================
            // ACHIEVEMENTS
            // ================================
            echo '"achievements":{"data":[';

            $first = true;

            User::select('id', 'name', 'nickname', 'capote_count', 'bandeira_count')
                ->selectRaw('(COALESCE(capote_count,0) + COALESCE(bandeira_count,0)) AS total')
                ->orderByDesc('total')
                ->orderByDesc('bandeira_count')
                ->chunk(500, function ($users) use (&$first) {
                    foreach ($users as $u) {
                        if (!$first) echo ',';
                        $first = false;

                        echo json_encode([
                            'id'    => $u->id,
                            'name'  => $u->name,
                            'nickname' => $u->nickname,
                            'capoteCount'   => $u->capote_count,
                            'bandeiraCount' => $u->bandeira_count,
                            'total'         => $u->total,
                        ]);
                    }
                });

            echo ']}';

            echo '}';

        }, 200, [
            "Content-Type" => "application/json; charset=UTF-8",
            "Cache-Control" => "no-cache"
        ]);
    }
}
