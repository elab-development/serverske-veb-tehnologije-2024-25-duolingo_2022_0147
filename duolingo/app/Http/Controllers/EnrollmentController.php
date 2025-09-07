<?php

namespace App\Http\Controllers;

use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *   name="Enrollments",
 *   description="Role-based access: student (own), teacher (their courses), admin (all)"
 * )
 */

class EnrollmentController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/enrollments",
     *   tags={"Enrollments"},
     *   summary="List enrollments (auth). Student→own; Teacher→their courses; Admin→all",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="per_page", in="query", required=false,
     *     description="Page size (1–100); only items array is returned in payload",
     *     @OA\Schema(type="integer", minimum=1, maximum=100), example=15
     *   ),
     *   @OA\Parameter(
     *     name="course_id", in="query", required=false, description="Filter by course",
     *     @OA\Schema(type="integer"), example=12
     *   ),
     *   @OA\Parameter(
     *     name="student_id", in="query", required=false, description="(Admin only) filter by student",
     *     @OA\Schema(type="integer"), example=33
     *   ),
     *   @OA\Parameter(
     *     name="status[]", in="query", required=false, style="form", explode=true,
     *     description="Filter by status",
     *     @OA\Schema(type="array", @OA\Items(type="string", enum={"active","completed","cancelled"}))
     *   ),
     *   @OA\Parameter(
     *     name="search", in="query", required=false,
     *     description="(Teacher/Admin) search by student name",
     *     @OA\Schema(type="string"), example="Stefan"
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="enrollments",
     *         type="array",
     *         @OA\Items(
     *           oneOf={
     *             @OA\Schema(
     *               type="object",
     *               @OA\Property(property="id", type="integer", example=101),
     *               @OA\Property(property="course_id", type="integer", example=12),
     *               @OA\Property(property="student_id", type="integer", example=33),
     *               @OA\Property(property="status", type="string", enum={"active","completed","cancelled"}, example="active"),
     *               @OA\Property(property="course_title", type="string", example="German B1 – Conversation"),
     *               @OA\Property(property="student_name", type="string", example="Stefan")
     *             ),
     *             @OA\Schema(
     *               type="object",
     *               @OA\Property(property="course_id", type="integer", example=12),
     *               @OA\Property(property="course_title", type="string", example="German B1 – Conversation"),
     *               @OA\Property(property="student_id", type="integer", example=33),
     *               @OA\Property(property="student_name", type="string", example="Stefan"),
     *               @OA\Property(property="status", type="string", enum={"active","completed","cancelled"}, example="completed")
     *             )
     *           }
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Forbidden for this course (teacher scope)"),
     *   @OA\Response(response=404, description="No enrollments found.")
     * )
     */
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        // STUDENT
        if ($user->role === 'student') {
            $q = Enrollment::with(['course:id,title', 'student:id,name'])
                ->where('student_id', $user->id);

            if ($request->filled('status')) {
                $q->whereIn('status', (array) $request->input('status'));
            }
            if ($request->filled('course_id')) {
                $q->where('course_id', (int) $request->input('course_id'));
            }

            $enrollments = $q->orderByDesc('id')->paginate($perPage)->appends($request->query());
            if ($enrollments->total() === 0) {
                return response()->json('No enrollments found.', 404);
            }

            return response()->json([
                'enrollments' => EnrollmentResource::collection($enrollments->items()),
            ]);
        }

        // TEACHER
        if ($user->role === 'teacher') {
            $courseIds = Course::where('teacher_id', $user->id)->pluck('id');
            if ($courseIds->isEmpty()) {
                return response()->json('No enrollments found.', 404);
            }

            $q = Enrollment::with(['course:id,title', 'student:id,name'])
                ->whereIn('course_id', $courseIds);

            if ($request->filled('course_id')) {
                $cid = (int) $request->input('course_id');
                if (!$courseIds->contains($cid)) {
                    return response()->json(['error' => 'Forbidden for this course'], 403);
                }
                $q->where('course_id', $cid);
            }
            if ($request->filled('status')) {
                $q->whereIn('status', (array) $request->input('status'));
            }

            if ($request->filled('search')) {
                $s = trim((string) $request->input('search'));
                $q->whereHas('student', fn($qq) => $qq->where('name', 'like', "%{$s}%"));
            }

            $rows = $q->orderByDesc('id')->paginate($perPage)->appends($request->query());
            if ($rows->total() === 0) {
                return response()->json('No enrollments found.', 404);
            }

            $list = collect($rows->items())->map(fn($e) => [
                'course_id' => $e->course_id,
                'course_title' => $e->course?->title,
                'student_id' => $e->student_id,
                'student_name' => $e->student?->name,
                'status' => $e->status,
            ])->values();

            return response()->json([
                'enrollments' => $list,
            ]);
        }

        // ADMIN
        if ($user->role === 'admin') {
            $q = Enrollment::with(['course:id,title', 'student:id,name']);

            if ($request->filled('course_id')) {
                $q->where('course_id', (int) $request->input('course_id'));
            }
            if ($request->filled('student_id')) {
                $q->where('student_id', (int) $request->input('student_id'));
            }
            if ($request->filled('status')) {
                $q->whereIn('status', (array) $request->input('status'));
            }
            if ($request->filled('search')) {
                $s = trim((string) $request->input('search'));
                $q->whereHas('student', fn($qq) => $qq->where('name', 'like', "%{$s}%"));
            }

            $enrollments = $q->orderByDesc('id')->paginate($perPage)->appends($request->query());
            if ($enrollments->total() === 0) {
                return response()->json('No enrollments found.', 404);
            }

            return response()->json([
                'enrollments' => EnrollmentResource::collection($enrollments->items()),
            ]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

   /**
     * @OA\Get(
     *   path="/api/student/{id}/enrollments",
     *   tags={"Enrollments"},
     *   summary="List all enrollments for a specific student (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id", in="path", required=true, description="Student ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="student",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=33),
     *         @OA\Property(property="name", type="string", example="Stefan"),
     *         @OA\Property(property="email", type="string", example="stefan@mail")
     *       ),
     *       @OA\Property(
     *         property="enrollments",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=101),
     *           @OA\Property(property="course_id", type="integer", example=12),
     *           @OA\Property(property="student_id", type="integer", example=33),
     *           @OA\Property(property="status", type="string", enum={"active","completed","cancelled"}, example="completed"),
     *           @OA\Property(property="course_title", type="string", example="German B1 – Conversation"),
     *           @OA\Property(property="student_name", type="string", example="Stefan")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only admins can access this resource"),
     *   @OA\Response(response=404, description="Student not found or no enrollments")
     * )
     */
     public function studentEnrollments($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can access this resource'], 403);
        }

        $student = User::where('id', (int) $id)->where('role', 'student')->first();
        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $enrollments = Enrollment::with(['course:id,title', 'student:id,name'])
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->get();

        if ($enrollments->isEmpty()) {
            return response()->json('No enrollments found for this student.', 404);
        }

        return response()->json([
            'student'     => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
            ],
            'enrollments' => EnrollmentResource::collection($enrollments),
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
     *   path="/api/enrollments",
     *   tags={"Enrollments"},
     *   summary="Create an enrollment (student only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"course_id"},
     *       @OA\Property(property="course_id", type="integer", example=12)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Enrollment created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Enrollment created successfully"),
     *       @OA\Property(
     *         property="enrollment",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=101),
     *         @OA\Property(property="course_id", type="integer", example=12),
     *         @OA\Property(property="student_id", type="integer", example=33),
     *         @OA\Property(property="status", type="string", enum={"active","completed","cancelled"}, example="active"),
     *         @OA\Property(property="course_title", type="string", example="German B1 – Conversation"),
     *         @OA\Property(property="student_name", type="string", example="Stefan")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only students can create enrollments"),
     *   @OA\Response(response=409, description="Already enrolled in this course"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        if ($user->role !== 'student') {
            return response()->json(['error' => 'Only students can create enrollments'], 403);
        }

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $exists = Enrollment::where('course_id', $validated['course_id'])
            ->where('student_id', $user->id)
            ->exists();
        if ($exists) {
            return response()->json(['error' => 'Already enrolled in this course'], 409);
        }

        $enrollment = Enrollment::create([
            'course_id' => (int) $validated['course_id'],
            'student_id' => $user->id,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Enrollment created successfully',
            'enrollment'  => new EnrollmentResource($enrollment->load(['course:id,title', 'student:id,name'])),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Enrollment $enrollment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Enrollment $enrollment)
    {
        //
    }

     /**
     * @OA\Put(
     *   path="/api/enrollments/{enrollment}",
     *   tags={"Enrollments"},
     *   summary="Update enrollment status (admin or course teacher)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="enrollment", in="path", required=true, description="Enrollment ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"status"},
     *       @OA\Property(property="status", type="string", enum={"active","completed","cancelled"}, example="completed")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Enrollment updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Enrollment updated successfully"),
     *       @OA\Property(
     *         property="enrollment",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=101),
     *         @OA\Property(property="course_id", type="integer", example=12),
     *         @OA\Property(property="student_id", type="integer", example=33),
     *         @OA\Property(property="status", type="string", enum={"active","completed","cancelled"}, example="completed"),
     *         @OA\Property(property="course_title", type="string", example="German B1 – Conversation"),
     *         @OA\Property(property="student_name", type="string", example="Stefan")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Enrollment $enrollment)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $enrollment->load('course:id,teacher_id');

        $isAdmin = $user->role === 'admin';
        $isTeacher = $user->role === 'teacher' && (int) $enrollment->course->teacher_id === (int) $user->id;

        if (!$isAdmin && !$isTeacher) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:active,completed,cancelled',
        ]);

        $enrollment->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Enrollment updated successfully',
            'enrollment' => new EnrollmentResource($enrollment->fresh()->load(['course:id,title', 'student:id,name'])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Enrollment $enrollment)
    {
        //
    }
}
