<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        // Ensure the course has a teacher so we can bind teacher_id cleanly
        $course = Course::factory()->withTeacher()->create();

        $start  = Carbon::instance(fake()->dateTimeBetween('+1 days', '+2 months'));
        $end  = (clone $start)->addMinutes(90);

        return [
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'title'  => 'Lesson: ' . fake()->words(3, true),
            'starts_at' => $start,
            'ends_at' => $end,
        ];
    }
}
