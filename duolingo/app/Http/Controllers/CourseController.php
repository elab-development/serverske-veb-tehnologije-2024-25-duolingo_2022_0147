<?php

namespace App\Http\Controllers;

use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *   name="Courses",
 *   description="Public listing/show; admin-only create/update/delete; admin can fetch courses by teacher"
 * )
 */
class CourseController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/courses",
     *   tags={"Courses"},
     *   summary="List all courses (public)",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="courses",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=12),
     *           @OA\Property(property="title", type="string", example="German B1 – Conversation"),
     *           @OA\Property(property="language", type="string", example="German"),
     *           @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}, example="B1"),
     *           @OA\Property(property="teacher_id", type="integer", nullable=true, example=5),
     *           @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No courses found.")
     * )
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
     * @OA\Get(
     *   path="/api/courses/teacher/{id}",
     *   tags={"Courses"},
     *   summary="List courses taught by a specific teacher (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id", in="path", required=true, description="Teacher ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="teacher",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=5),
     *         @OA\Property(property="name", type="string", example="Stefan"),
     *         @OA\Property(property="email", type="string", example="stefan@mail")
     *       ),
     *       @OA\Property(
     *         property="courses",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=12),
     *           @OA\Property(property="title", type="string", example="German B1 – Conversation"),
     *           @OA\Property(property="language", type="string", example="German"),
     *           @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}, example="B1"),
     *           @OA\Property(property="teacher_id", type="integer", nullable=true, example=5),
     *           @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only admins can access this resource"),
     *   @OA\Response(response=404, description="Teacher not found or no courses for this teacher")
     * )
     */
     public function teacherCourses($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can access this resource'], 403);
        }

        $teacher = User::where('id', (int) $id)->where('role', 'teacher')->first();
        if (!$teacher) {
            return response()->json(['error' => 'Teacher not found'], 404);
        }

        $courses = Course::where('teacher_id', $teacher->id)
            ->orderByDesc('is_active')
            ->orderBy('title')
            ->get();

        if ($courses->isEmpty()) {
            return response()->json('No courses found for this teacher.', 404);
        }

        return response()->json([
            'teacher' => [
                'id'   => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
            ],
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
     * @OA\Post(
     *   path="/api/courses",
     *   tags={"Courses"},
     *   summary="Create a new course (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"title","language","level"},
     *       @OA\Property(property="title", type="string", maxLength=255, example="German B1 – Conversation"),
     *       @OA\Property(property="language", type="string", maxLength=50, example="German"),
     *       @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}, example="B1"),
     *       @OA\Property(property="teacher_id", type="integer", nullable=true, example=5),
     *       @OA\Property(property="is_active", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Course created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Course created successfully"),
     *       @OA\Property(
     *         property="course",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=12),
     *         @OA\Property(property="title", type="string", example="German B1 – Conversation"),
     *         @OA\Property(property="language", type="string", example="German"),
     *         @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}, example="B1"),
     *         @OA\Property(property="teacher_id", type="integer", nullable=true, example=5),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only admins can create courses"),
     *   @OA\Response(response=422, description="Validation error")
     * )
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
     * @OA\Get(
     *   path="/api/courses/{course}",
     *   tags={"Courses"},
     *   summary="Get a single course (public)",
     *   @OA\Parameter(
     *     name="course", in="path", required=true, description="Course ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="course",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=12),
     *         @OA\Property(property="title", type="string", example="German B1 – Conversation"),
     *         @OA\Property(property="language", type="string", example="German"),
     *         @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}, example="B1"),
     *         @OA\Property(property="teacher_id", type="integer", nullable=true, example=5),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="Course not found")
     * )
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
     * @OA\Put(
     *   path="/api/courses/{course}",
     *   tags={"Courses"},
     *   summary="Update a course (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="course", in="path", required=true, description="Course ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="title", type="string", maxLength=255, example="German B2 – Intensive"),
     *       @OA\Property(property="language", type="string", maxLength=50, example="German"),
     *       @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}, example="B2"),
     *       @OA\Property(property="teacher_id", type="integer", nullable=true, example=8),
     *       @OA\Property(property="is_active", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Course updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Course updated successfully"),
     *       @OA\Property(
     *         property="course",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=12),
     *         @OA\Property(property="title", type="string", example="German B2 – Intensive"),
     *         @OA\Property(property="language", type="string", example="German"),
     *         @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}, example="B2"),
     *         @OA\Property(property="teacher_id", type="integer", nullable=true, example=8),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only admins can update courses"),
     *   @OA\Response(response=422, description="Validation error")
     * )
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
     * @OA\Delete(
     *   path="/api/courses/{course}",
     *   tags={"Courses"},
     *   summary="Delete a course (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="course", in="path", required=true, description="Course ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Deleted",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Course deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only admins can delete courses")
     * )
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
