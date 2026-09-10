<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetFinanceRoleRequest;
use App\Services\FinanceAuthorizationService;
use App\Services\FinanceRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceRoleController extends Controller
{
    public function __construct(
        private readonly FinanceAuthorizationService $authorization,
        private readonly FinanceRoleService $roles,
    ) {
    }

    public function assign(SetFinanceRoleRequest $request, int $userId): JsonResponse
    {
        $user = $this->roles->assign($request->user(), $userId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'role' => $user->role,
            ],
        ]);
    }

    public function remove(Request $request, int $userId): JsonResponse
    {
        $this->authorization->assertAdmin($request->user());
        $user = $this->roles->remove($request->user(), $userId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'role' => $user->role,
            ],
        ]);
    }

    public function access(Request $request): JsonResponse
    {
        $this->authorization->assertFinance($request->user());

        return response()->json([
            'status' => 'success',
            'data' => ['role' => 'finance'],
        ]);
    }
}
