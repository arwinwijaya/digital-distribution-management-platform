<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterOutletRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Outlet;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RbacMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService, private readonly RbacMatrixService $rbacMatrix)
    {
        $this->authService = $authService;
    }

    /**
     * Register an outlet user and bind the outlet in one server-side transaction.
     */
    public function registerOutlet(RegisterOutletRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tokenData = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => $validated['phone'],
                'role' => 'outlet',
                'is_active' => true,
            ]);

            $outlet = Outlet::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'district' => $validated['district'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return [$user, $outlet, $this->authService->createToken($user)];
        });

        [$user, $outlet, $tokenData] = $tokenData;

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $tokenData['token'],
                'token_type' => 'Bearer',
                'expires_in' => $tokenData['expires_in'],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'outlet' => $outlet,
            ],
        ], 201);
    }

    /**
     * Handle user login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $tokenData = $this->authService->createToken($user);

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $tokenData['token'],
                'token_type' => 'Bearer',
                'expires_in' => $tokenData['expires_in'],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ],
        ]);
    }

    /**
     * Get authenticated user profile
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
                // Full 19-key map for the caller's role; missing rows are `none`.
                'rbac' => $this->rbacMatrix->mapForRole($user->role),
            ],
        ]);
    }

    /**
     * Update authenticated user profile (name / email only).
     * Role field must NOT be accepted here — use role assignment endpoint.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'    => $user->id,
                'name'  => $user->fresh()->name,
                'email' => $user->fresh()->email,
                'role'  => $user->fresh()->role,
            ],
        ]);
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $this->authService->invalidateToken($request->user(), $token);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Refresh authentication token
     */
    public function refresh(Request $request): JsonResponse
    {
        $tokenData = $this->authService->refreshToken($request->user());

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $tokenData['token'],
                'token_type' => 'Bearer',
                'expires_in' => $tokenData['expires_in'],
            ],
        ]);
    }
}
