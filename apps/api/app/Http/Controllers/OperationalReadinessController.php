<?php

namespace App\Http\Controllers;

use App\Services\OperationalIssueService;
use App\Services\OperationalReadinessService;
use App\Http\Requests\ListOperationalIssuesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalReadinessController extends Controller
{
    public function __construct(
        private readonly OperationalReadinessService $readinessService,
        private readonly OperationalIssueService $issueService,
    ) {
    }

    public function readiness(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view operational readiness.',
            ], 403);
        }

        $payload = $this->readinessService->readiness($request);

        return response()->json([
            'status' => 'success',
            'data' => $payload,
        ]);
    }

    public function issues(ListOperationalIssuesRequest $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view operational issues.',
            ], 403);
        }

        $result = $this->issueService->list($request->validatedData());

        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function issueDetail(Request $request, int $id): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view operational issues.',
            ], 403);
        }

        $issue = $this->issueService->findById($id);
        if ($issue === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Issue not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $issue,
        ]);
    }
}
