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
        Schema::table('lessons', function (Blueprint $table) {
            // Remove computed columns if they exist
            if (Schema::hasColumn('lessons', 'can_access')) {
                $table->dropColumn('can_access');
            }
            if (Schema::hasColumn('lessons', 'has_video')) {
                $table->dropColumn('has_video');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // We don't want to add these back as they should be computed
        });
    }
};
