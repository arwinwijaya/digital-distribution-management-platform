<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRbacMatrixRequest;
use App\Services\FinanceAuthorizationService;
use App\Services\RbacMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RBAC role x menu matrix administration.
 *
 *   GET /admin/rbac/matrix — view the full 7-role x 21-menu matrix
 *   PUT /admin/rbac/matrix — update one or more cells (all-or-nothing)
 *
 * Both endpoints are authorized at the controller level (owner+admin) rather
 * than via `rbac:` middleware — decision K-A: the spec's default matrix gives
 * `admin -> rbac_matrix = read`, but Story 2 requires admin to be able to PUT.
 * The matrix level therefore governs menu *visibility*, not this endpoint's
 * authorization.
 */
class RbacMatrixController extends Controller
{
    public function __construct(
        private readonly RbacMatrixService $matrix,
        private readonly FinanceAuthorizationService $auth,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->auth->assertAdminOrOwner($request->user());

        return response()->json([
            'status' => 'success',
            'data'   => $this->matrix->fullMatrix(),
        ]);
    }

    public function update(UpdateRbacMatrixRequest $request): JsonResponse
    {
        $this->auth->assertAdminOrOwner($request->user());

        $this->matrix->applyCells($request->user(), $request->cells());

        return response()->json([
            'status' => 'success',
            'data'   => $this->matrix->fullMatrix(),
        ]);
    }
}
