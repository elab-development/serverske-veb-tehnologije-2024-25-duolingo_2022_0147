<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentIds = User::where('role', 'student')->pluck('id')->all();

        Course::query()->chunk(50, function ($courses) use ($studentIds) {
            foreach ($courses as $course) {
                $take = rand(10, 25);
                $chosen = collect($studentIds)->shuffle()->take($take);

                foreach ($chosen as $sid) {
                    Enrollment::create([
                        'course_id' => $course->id,
                        'student_id' => $sid,
                        'status' => collect(['active', 'completed', 'cancelled'])->random(),
                    ]);
                }
            }
        });
    }
}
