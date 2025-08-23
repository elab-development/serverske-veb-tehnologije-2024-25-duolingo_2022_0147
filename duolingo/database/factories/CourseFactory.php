<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $language = fake()->randomElement(['English', 'German', 'Spanish', 'French', 'Italian', 'Serbian']);
        $level  = fake()->randomElement(['A1', 'A2', 'B1', 'B2', 'C1', 'C2']);
        $suffix  = fake()->randomElement(['General', 'Conversation', 'Grammar', 'Intensive', 'Workshop']);

        return [
            'title'      => "{$language} {$level} – {$suffix}",
            'language'   => $language,
            'level'      => $level,
            'teacher_id' => null,
            'is_active'  => true,
        ];
    }

    /** Helper to create with a teacher assigned */
    public function withTeacher(?User $teacher = null): static
    {
        return $this->state(function () use ($teacher) {
            return [
                'teacher_id' => ($teacher?->id) ?? User::factory()->teacher(),
            ];
        });
    }
}
