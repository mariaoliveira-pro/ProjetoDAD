<?php
namespace App\Http\Controllers;

use App\Models\ShopItem;
use App\Models\User;
use App\Models\UserInventory;
use Illuminate\Http\Request; // Assumindo que já tens o modelo User
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    // GET /api/shop/items?email=...
    public function index(Request $request)
    {
        $email = $request->query('email');

        // Busca todos os itens da loja
        $shopItems = ShopItem::all();

        // Busca o que este user já comprou
        $myInventory = UserInventory::where('user_email', $email)
            ->pluck('item_resource_name')
            ->toArray();

        // Formata a resposta para o Android
        $formattedItems = $shopItems->map(function ($item) use ($myInventory) {
            return [
                'name'         => $item->name,

                // AQUI: A BD tem resource_name, mas tu envias resourceName (camelCase)
                // Isto significa que no Android tens de usar resourceName também!
                'resourceName' => $item->resource_name,

                'price'        => $item->price,
                'isPurchased'  => in_array($item->resource_name, $myInventory),

                // ⚠️ ESTA LINHA FALTAVA! Sem isto o Android não mostra nada.
                'type'         => $item->type,
            ];
        });

        return response()->json($formattedItems);
    }

    // POST /api/shop/buy
    public function buy(Request $request)
    {
        // O Android envia: email, item_id (resource_name), price
        $email  = $request->input('email');
        $itemId = $request->input('item_id');
        $price  = $request->input('price');

        // 1. Verificar User e Saldo
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if ($user->coins < $price) {
            return response()->json(['error' => 'Insufficient coins'], 400);
        }

        // 2. Verificar se já tem o item
        $exists = UserInventory::where('user_email', $email)
            ->where('item_resource_name', $itemId)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Item already purchased'], 400);
        }

        // 3. Transação (Descontar dinheiro e dar item)
        try {
            DB::beginTransaction();

            // Atualiza saldo
            $user->coins -= $price;
            $user->save(); // Laravel sabe fazer o UPDATE users SET coins...

            // Adiciona ao inventário
            UserInventory::create([
                'user_email'         => $email,
                'item_resource_name' => $itemId,
            ]);

            DB::commit();

            return response()->json([
                'message'     => 'Purchase successful',
                'new_balance' => $user->coins,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Transaction failed'], 500);
        }
    }
}
