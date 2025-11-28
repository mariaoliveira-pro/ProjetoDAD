<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInventory extends Model
{
    protected $table = 'user_inventory';
    public $timestamps = false;

    protected $fillable = ['user_email', 'item_resource_name', 'purchase_date'];
}
