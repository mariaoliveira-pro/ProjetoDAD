<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShopItem;
use App\Models\UserInventory;

class InventoryController extends Controller
{
    // GET /api/users/inventory?email=...
    public function index(Request $request)
    {
        $email = $request->query('email');

        // Faz um JOIN para saber o TIPO do item (deck ou avatar)
        // SQL equivalente: SELECT s.resource_name, s.type FROM user_inventory i JOIN shop_items s ...
        $inventoryItems = UserInventory::join('shop_items', 'user_inventory.item_resource_name', '=', 'shop_items.resource_name')
            ->where('user_inventory.user_email', $email)
            ->get(['shop_items.resource_name', 'shop_items.type']);

        // Separa em duas listas
        $decks = [];
        $avatars = [];

        foreach ($inventoryItems as $item) {
            if ($item->type === 'deck') {
                $decks[] = $item->resource_name;
            } elseif ($item->type === 'avatar') {
                $avatars[] = $item->resource_name;
            }
        }

        // Garante os defaults
        if (!in_array('deck1_preview', $decks)) array_unshift($decks, 'deck1_preview');
        if (!in_array('default_avatar', $avatars)) array_unshift($avatars, 'default_avatar');

        return response()->json([
            'decks' => $decks,
            'avatars' => $avatars
        ]);
    }
}
