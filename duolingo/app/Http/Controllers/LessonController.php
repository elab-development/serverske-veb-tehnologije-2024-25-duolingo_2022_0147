<?php

namespace App\Http\Controllers;

use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $q = Lesson::query()
            ->with(['teacher:id,name', 'course:id,title']);

        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $q->where('title', 'like', "%{$s}%");
        }

        if ($request->filled('teacher_id')) {
            $q->where('teacher_id', (int) $request->input('teacher_id'));
        }
        if ($request->filled('course_id')) {
            $q->where('course_id', (int) $request->input('course_id'));
        }

        $sortBy  = $request->input('sort_by', 'starts_at');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $sortable = ['starts_at', 'title', 'created_at'];
        if (!in_array($sortBy, $sortable, true)) {
            $sortBy = 'starts_at';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        $q->orderBy($sortBy, $sortDir);

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $lessons = $q->paginate($perPage)->appends($request->query());

        if ($lessons->total() === 0) {
            return response()->json('No lessons found.', 404);
        }

        return response()->json([
            'lessons' => LessonResource::collection($lessons->items()),
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
        $user = Auth::user();
        if ($user->role !== 'teacher') {
            return response()->json(['error' => 'Only teachers can create lessons'], 403);
        }

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        if ((int) $course->teacher_id !== (int) $user->id) {
            return response()->json(['error' => 'You are not the teacher of this course'], 403);
        }

        $lesson = Lesson::create([
            'course_id' => $course->id,
            'teacher_id' => $user->id,
            'title' => $validated['title'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'Lesson created successfully',
            'lesson' => new LessonResource($lesson->load(['teacher:id,name', 'course:id,title'])),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lesson $lesson)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $lesson->load(['teacher:id,name', 'course:id,title']);

        return response()->json([
            'lesson' => new LessonResource($lesson),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Lesson $lesson)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lesson $lesson)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $user = Auth::user();
        if ($user->role !== 'teacher') {
            return response()->json(['error' => 'Only teachers can update lessons'], 403);
        }

        $lesson->load('course:id,teacher_id');
        if ((int) $lesson->teacher_id !== (int) $user->id || (int) $lesson->course->teacher_id !== (int) $user->id) {
            return response()->json(['error' => 'You are not the teacher of this course'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        unset($validated['course_id'], $validated['teacher_id']);

        if (empty($validated)) {
            return response()->json(['error' => 'No editable fields provided'], 422);
        }

        $lesson->update($validated);

        return response()->json([
            'message' => 'Lesson updated successfully',
            'lesson' => new LessonResource($lesson->fresh()->load(['teacher:id,name', 'course:id,title'])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $user = Auth::user();
        if ($user->role !== 'teacher') {
            return response()->json(['error' => 'Only teachers can delete lessons'], 403);
        }

        $lesson->load('course:id,teacher_id');
        if ((int) $lesson->teacher_id !== (int) $user->id || (int) $lesson->course->teacher_id !== (int) $user->id) {
            return response()->json(['error' => 'You are not the teacher of this course'], 403);
        }

        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted successfully']);
    }
}
