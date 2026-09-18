<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xc_vm_mappings', function (Blueprint $table) {
            $table->id();
            // Local entity this mapping refers to (e.g. 'channel', 'user', 'bouquet').
            $table->string('entity_type', 50);
            // Local primary key of the entity.
            $table->unsignedBigInteger('entity_id');
            // The ID returned by the XC-VM admin API for the mirrored object.
            $table->unsignedBigInteger('xc_vm_id');
            // Optional metadata payload (JSON) captured at sync time.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id']);
            $table->index(['entity_type', 'xc_vm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xc_vm_mappings');
    }
};