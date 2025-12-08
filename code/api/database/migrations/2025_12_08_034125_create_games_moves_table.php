<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('game_moves', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('game_id'); // <--- Agora liga ao JOGO, não ao Match
        $table->integer('round_number');       // 1 a 10
        $table->string('player_card', 2);      // ex: "ac"
        $table->string('bot_card', 2);         // ex: "7o"
        $table->string('winner');              // "player" ou "bot"
        $table->integer('points_earned');
        $table->timestamps();
        
        // Se tiveres uma tabela 'games', podes por a foreign key, senão deixa estar assim
        $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games_moves');
    }
};
