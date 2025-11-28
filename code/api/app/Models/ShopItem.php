<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    // Define o nome exato da tabela que criaste no SQLite
    protected $table = 'shop_items';

    // Desativa timestamps se não tiveres created_at/updated_at na tabela
    public $timestamps = false;

    protected $fillable = ['type', 'name', 'resource_name', 'price'];
}
