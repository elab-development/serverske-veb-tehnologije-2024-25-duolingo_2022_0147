<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'teacher_id' => $this->teacher_id,
            'title' => $this->title,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'teacher'  => [
                'id' => optional($this->teacher)->id,
                'name' => optional($this->teacher)->name,
            ],
        ];
    }
}
