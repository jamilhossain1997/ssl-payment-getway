<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Your internal order reference (unique per attempt)
            $table->string('tran_id')->unique();

            // Optional: link to your own orders table
            $table->unsignedBigInteger('order_id')->nullable()->index();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('BDT');

            // pending | processing | success | failed | cancelled | refunded
            $table->string('status')->default('pending')->index();

            $table->string('val_id')->nullable();      // returned by SSLCommerz on success
            $table->string('bank_tran_id')->nullable();
            $table->string('card_type')->nullable();
            $table->string('card_issuer')->nullable();

            $table->json('raw_init_response')->nullable();
            $table->json('raw_ipn_payload')->nullable();
            $table->json('raw_validation_response')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
