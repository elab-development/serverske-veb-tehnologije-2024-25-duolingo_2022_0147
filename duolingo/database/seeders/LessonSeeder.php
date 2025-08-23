<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LessonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coursesWithTeacher = Course::whereNotNull('teacher_id')->get();

        foreach ($coursesWithTeacher as $course) {
            $count = rand(6, 10);

            for ($i = 0; $i < $count; $i++) {
                $start = Carbon::now()->addDays(rand(2, 60))->setTime(rand(9, 19), [0, 30][rand(0, 1)]);
                $end   = (clone $start)->addMinutes([60, 90, 120][rand(0, 2)]);

                Lesson::create([
                    'course_id' => $course->id,
                    'teacher_id' => $course->teacher_id,
                    'title'  => 'Lesson: ' . fake()->words(3, true),
                    'starts_at' => $start,
                    'ends_at' => $end,
                ]);
            }
        }
    }
}
