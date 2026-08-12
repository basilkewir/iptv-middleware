<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streaming_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(80);
            $table->string('protocol')->default('hls');
            $table->boolean('is_active')->default(true);
            $table->integer('max_connections')->default(1000);
            $table->integer('current_connections')->default(0);
            $table->bigInteger('bandwidth')->default(0);
            $table->string('location')->nullable();
            $table->string('provider')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaming_servers');
    }
};
