<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'title'  => $this->title,
            'language' => $this->language,
            'level'  => $this->level,
            'teacher_id' => $this->teacher_id,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
