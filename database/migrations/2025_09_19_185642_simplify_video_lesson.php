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
            // إزالة الأعمدة غير المستخدمة في النظام الجديد
            $table->dropColumn([
                'video_token',
                'video_token_expires_at', 
                'is_video_protected',
                'video_metadata'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_token')->nullable();
            $table->timestamp('video_token_expires_at')->nullable();
            $table->boolean('is_video_protected')->default(true);
            $table->json('video_metadata')->nullable();
        });
    }
};
