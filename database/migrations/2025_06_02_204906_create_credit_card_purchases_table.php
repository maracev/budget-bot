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
        Schema::create('credit_card_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('owner_name')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('vendor');
            $table->string('card_name')->nullable();
            $table->string('billing_cycle');
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();
        
            $table->index(['owner_id']);
            $table->index(['billing_cycle']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_card_purchases');
    }
};
