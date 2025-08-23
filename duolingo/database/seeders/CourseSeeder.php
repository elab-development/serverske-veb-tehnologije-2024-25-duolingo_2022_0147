<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teachers = User::where('role', 'teacher')->pluck('id');

        for ($i = 0; $i < 12; $i++) {
            Course::factory()->create([
                'teacher_id' => $teachers->random(),
            ]);
        }

        Course::factory()->count(4)->create(['teacher_id' => null]);
    }
}
