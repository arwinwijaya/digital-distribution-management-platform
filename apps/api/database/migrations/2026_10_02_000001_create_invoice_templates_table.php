<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->string('company_name');
            $table->string('address');
            $table->string('npwp');
            $table->string('primary_color');
            $table->text('footer_text')->nullable();
            $table->text('notes')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_title')->nullable();
            $table->boolean('show_npwp')->default(true);
            $table->boolean('show_outlet_phone')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
