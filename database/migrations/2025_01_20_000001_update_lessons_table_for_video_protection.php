
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
            // إضافة حقول الحماية للفيديو
            $table->string('video_token')->nullable()->comment('رمز الحماية للفيديو');
            $table->timestamp('video_token_expires_at')->nullable()->comment('تاريخ انتهاء صلاحية الرمز');
            $table->boolean('is_video_protected')->default(true)->comment('هل الفيديو محمي');
            $table->json('video_metadata')->nullable()->comment('معلومات إضافية عن الفيديو');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'video_token',
                'video_token_expires_at',
                'is_video_protected',
                'video_metadata'
            ]);
        });
    }
};
