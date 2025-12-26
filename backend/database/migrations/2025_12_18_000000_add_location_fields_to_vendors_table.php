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
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('formatted_address')->nullable()->after('description');
            $table->decimal('lat', 10, 7)->nullable()->after('formatted_address');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['formatted_address', 'lat', 'lng']);
        });
    }
};

