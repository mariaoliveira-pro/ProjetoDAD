<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShopItem;
use App\Models\UserInventory;
use App\Models\User; // Necessário para buscar o ID

class InventoryController extends Controller
{
    // GET /api/users/inventory?email=...
    public function index(Request $request)
    {
        $email = $request->query('email');

        // 1. Encontrar o User para saber o ID
        $user = User::where('email', $email)->first();

        // Se o user não existir, retornamos apenas os defaults para não dar erro no Android
        if (!$user) {
            return response()->json([
                'decks' => ['deck1_preview'],
                'avatars' => ['default_avatar']
            ]);
        }

        // 2. Faz um JOIN usando o USER_ID
         $inventoryItems = UserInventory::join('shop_items', 'user_inventory.item_resource_name', '=', 'shop_items.resource_name')
            ->where('user_inventory.user_id', $user->id)
            ->get(['shop_items.resource_name', 'shop_items.type']);

        // Separa em duas listas
        $decks = [];
        $avatars = [];

         foreach ($inventoryItems as $item) {
            if ($item->type === 'deck') $decks[] = $item->resource_name;
            elseif ($item->type === 'avatar') $avatars[] = $item->resource_name;
        }

        // Garante os defaults
        if (!in_array('deck1_preview', $decks)) array_unshift($decks, 'deck1_preview');
        if (!in_array('default_avatar', $avatars)) array_unshift($avatars, 'default_avatar');

        return response()->json([
            'decks' => $decks,
            'avatars' => $avatars
        ]);
    }

    // POST /api/users/equip
    public function equip(Request $request)
    {
        // Validação dos dados que vêm do Android
        $request->validate([
            'email' => 'required|email',
            'type' => 'required|in:deck,avatar',
            'resource_name' => 'required|string'
        ]);

        $email = $request->input('email');
        $type = $request->input('type'); // "deck" ou "avatar"
        $resourceName = $request->input('resource_name'); // ex: "deck_fire"

        // 1. Encontrar o User
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // 2. Verificar se o user TEM o item no inventário
        // (Permitimos sempre os itens default "deck1_preview" e "default_avatar")
        $hasItem = UserInventory::where('user_id', $user->id)
            ->where('item_resource_name', $resourceName)
            ->exists();

        if (!$hasItem && $resourceName !== 'deck1_preview' && $resourceName !== 'default_avatar') {
            return response()->json(['error' => 'Não tens este item no inventário'], 403);
        }

        // 3. Atualizar a tabela users (current_deck ou current_avatar)
        if ($type === 'deck') {
            $user->current_deck = $resourceName;
        } else {
            $user->current_avatar = $resourceName;
        }

        $user->save(); // O Laravel faz o UPDATE SQL automaticamente aqui

        return response()->json([
            'message' => 'Equipado com sucesso',
            'current_item' => $resourceName
        ]);
    }
}
