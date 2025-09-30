<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fungi', function (Blueprint $table) {
            $table->dropColumn('kingdom');
            $table->dropColumn('phylum');
            $table->dropColumn('class');
            $table->dropColumn('order');
            $table->dropColumn('family');
            $table->dropColumn('genus');
            $table->dropColumn('specie');
            $table->dropColumn('authors');
            $table->dropColumn('scientific_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fungi', function (Blueprint $table) {
            $table->string('kingdom')->nullable();
            $table->string('phylum')->nullable();
            $table->string('class')->nullable();
            $table->string('order')->nullable();
            $table->string('family')->nullable();
            $table->string('genus')->nullable();
            $table->string('specie')->nullable();
            $table->string('authors')->nullable();
            $table->string('scientific_name')->nullable();
        });
    }
};
