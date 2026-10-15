<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy building.deed {typeid[], no[], source[], date[]} and building.plots {no[], size[], <side>_type/_plotno/_length/_width[]}
 * held several title deeds and land plots per unit; building_borders.border is the code those plots reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('border_types', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->after('id');   // legacy border: street, path, neighbour, build...
        });

        Schema::create('unit_deeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deed_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 100);
            $table->string('source')->nullable();                  // issuing court / notary
            $table->string('issue_date', 20)->nullable();          // as written on the deed (often Hijri)
            $table->timestamps();
        });

        Schema::create('unit_plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50)->nullable();
            $table->decimal('area', 12, 2)->nullable();
            $table->json('sides')->nullable();                     // {north: {type, plot_number, length, width}, east: …, south: …, west: …}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_plots');
        Schema::dropIfExists('unit_deeds');
        Schema::table('border_types', fn (Blueprint $table) => $table->dropColumn('code'));
    }
};
