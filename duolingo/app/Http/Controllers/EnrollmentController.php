<?php

namespace App\Http\Controllers;

use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    /**
     * Display a listing of the resource.
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
     * Update the specified resource in storage.
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
