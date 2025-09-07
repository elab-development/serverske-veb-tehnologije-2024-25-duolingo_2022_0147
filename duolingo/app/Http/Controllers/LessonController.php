<?php

namespace App\Http\Controllers;

use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *   name="Lessons",
 *   description="Authenticated listing/show; teachers can create/update/delete only on their own courses"
 * )
 */
class LessonController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/lessons",
     *   tags={"Lessons"},
     *   summary="List lessons (auth required) with search, filters, sort & pagination",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="search", in="query", required=false, description="Search in lesson title",
     *     @OA\Schema(type="string"), example="Past tense"
     *   ),
     *   @OA\Parameter(
     *     name="teacher_id", in="query", required=false, description="Filter by teacher ID",
     *     @OA\Schema(type="integer"), example=7
     *   ),
     *   @OA\Parameter(
     *     name="course_id", in="query", required=false, description="Filter by course ID",
     *     @OA\Schema(type="integer"), example=12
     *   ),
     *   @OA\Parameter(
     *     name="sort_by", in="query", required=false,
     *     description="Sort field (allowed: starts_at, title, created_at)",
     *     @OA\Schema(type="string", enum={"starts_at","title","created_at"}), example="starts_at"
     *   ),
     *   @OA\Parameter(
     *     name="sort_dir", in="query", required=false,
     *     description="Sort direction",
     *     @OA\Schema(type="string", enum={"asc","desc"}), example="asc"
     *   ),
     *   @OA\Parameter(
     *     name="per_page", in="query", required=false,
     *     description="Page size (1–100). Returns only the items array in payload.",
     *     @OA\Schema(type="integer", minimum=1, maximum=100), example=10
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="lessons",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=101),
     *           @OA\Property(property="course_id", type="integer", example=12),
     *           @OA\Property(property="teacher_id", type="integer", example=7),
     *           @OA\Property(property="title", type="string", example="Unit 3: Past Tense"),
     *           @OA\Property(property="starts_at", type="string", format="date-time", example="2025-09-01T10:00:00Z"),
     *           @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2025-09-01T11:30:00Z"),
     *           @OA\Property(
     *             property="teacher", type="object",
     *             @OA\Property(property="id", type="integer", example=7),
     *             @OA\Property(property="name", type="string", example="Stefan")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=404, description="No lessons found.")
     * )
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
     * @OA\Post(
     *   path="/api/lessons",
     *   tags={"Lessons"},
     *   summary="Create a lesson (teacher only; must teach the course)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"course_id","title","starts_at"},
     *       @OA\Property(property="course_id", type="integer", example=12),
     *       @OA\Property(property="title", type="string", maxLength=255, example="Unit 3: Past Tense"),
     *       @OA\Property(property="starts_at", type="string", format="date-time", example="2025-09-01T10:00:00Z"),
     *       @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2025-09-01T11:30:00Z")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Lesson created successfully"),
     *       @OA\Property(
     *         property="lesson", type="object",
     *         @OA\Property(property="id", type="integer", example=101),
     *         @OA\Property(property="course_id", type="integer", example=12),
     *         @OA\Property(property="teacher_id", type="integer", example=7),
     *         @OA\Property(property="title", type="string", example="Unit 3: Past Tense"),
     *         @OA\Property(property="starts_at", type="string", format="date-time", example="2025-09-01T10:00:00Z"),
     *         @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2025-09-01T11:30:00Z"),
     *         @OA\Property(
     *           property="teacher", type="object",
     *           @OA\Property(property="id", type="integer", example=7),
     *           @OA\Property(property="name", type="string", example="Stefan")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only teachers can create lessons / You are not the teacher of this course"),
     *   @OA\Response(response=422, description="Validation error")
     * )
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
     * @OA\Get(
     *   path="/api/lessons/{lesson}",
     *   tags={"Lessons"},
     *   summary="Get a single lesson (auth required)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="lesson", in="path", required=true, description="Lesson ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="lesson", type="object",
     *         @OA\Property(property="id", type="integer", example=101),
     *         @OA\Property(property="course_id", type="integer", example=12),
     *         @OA\Property(property="teacher_id", type="integer", example=7),
     *         @OA\Property(property="title", type="string", example="Unit 3: Past Tense"),
     *         @OA\Property(property="starts_at", type="string", format="date-time", example="2025-09-01T10:00:00Z"),
     *         @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2025-09-01T11:30:00Z"),
     *         @OA\Property(
     *           property="teacher", type="object",
     *           @OA\Property(property="id", type="integer", example=7),
     *           @OA\Property(property="name", type="string", example="Stefan")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=404, description="Lesson not found")
     * )
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
     * @OA\Put(
     *   path="/api/lessons/{lesson}",
     *   tags={"Lessons"},
     *   summary="Update a lesson (teacher only; must teach the course)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="lesson", in="path", required=true, description="Lesson ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="title", type="string", maxLength=255, example="Unit 3: Past Tense – Review"),
     *       @OA\Property(property="starts_at", type="string", format="date-time", example="2025-09-01T10:30:00Z"),
     *       @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2025-09-01T12:00:00Z")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Lesson updated successfully"),
     *       @OA\Property(
     *         property="lesson", type="object",
     *         @OA\Property(property="id", type="integer", example=101),
     *         @OA\Property(property="course_id", type="integer", example=12),
     *         @OA\Property(property="teacher_id", type="integer", example=7),
     *         @OA\Property(property="title", type="string", example="Unit 3: Past Tense – Review"),
     *         @OA\Property(property="starts_at", type="string", format="date-time", example="2025-09-01T10:30:00Z"),
     *         @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2025-09-01T12:00:00Z"),
     *         @OA\Property(
     *           property="teacher", type="object",
     *           @OA\Property(property="id", type="integer", example=7),
     *           @OA\Property(property="name", type="string", example="Stefan")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only teachers can update lessons / You are not the teacher of this course"),
     *   @OA\Response(response=422, description="Validation error")
     * )
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
     * @OA\Delete(
     *   path="/api/lessons/{lesson}",
     *   tags={"Lessons"},
     *   summary="Delete a lesson (teacher only; must teach the course)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="lesson", in="path", required=true, description="Lesson ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Deleted",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Lesson deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only teachers can delete lessons / You are not the teacher of this course")
     * )
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
