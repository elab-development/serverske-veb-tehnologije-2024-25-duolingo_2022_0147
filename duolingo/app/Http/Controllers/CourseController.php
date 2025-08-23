<?php

namespace App\Http\Controllers;

use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $courses = Course::query()
            ->orderByDesc('is_active')
            ->orderBy('title')
            ->get();

        if ($courses->isEmpty()) {
            return response()->json('No courses found.', 404);
        }

        return response()->json([
            'courses' => CourseResource::collection($courses),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create courses'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'language' => 'required|string|max:50',
            'level' => ['required', 'string', Rule::in(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])],
            'teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn($q) => $q->where('role', 'teacher')),
            ],
            'is_active'  => 'sometimes|boolean',
        ]);

        $course = Course::create($validated);

        return response()->json([
            'message' => 'Course created successfully',
            'course' => new CourseResource($course),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        return response()->json([
            'course' => new CourseResource($course),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update courses'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'language' => 'sometimes|string|max:50',
            'level' => ['sometimes', 'string', Rule::in(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])],
            'teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn($q) => $q->where('role', 'teacher')),
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully',
            'course' => new CourseResource($course->fresh()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete courses'], 403);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted successfully']);
    }
}
