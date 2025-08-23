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
            $table->index(['course_id', 'teacher_id']);
            $table->foreign('course_id')
                ->references('id')->on('courses')
                ->cascadeOnDelete();
            $table->foreign('teacher_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['teacher_id']);
            $table->dropIndex(['course_id', 'teacher_id']);
        });
    }
};
