<?php

namespace App\Http\Requests;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use App\Services\FinanceAuthorizationService;
use App\Services\RbacMatrixService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for PUT /admin/rbac/matrix.
 *
 * Body: { cells: [{ role, menu_key, level }, ...] }
 *   - cells must be a non-empty array
 *   - role in the 7 known roles
 *   - menu_key in the menu catalog
 *   - level in none|read|edit
 *
 * Authorization (owner+admin) is enforced by the controller via
 * assertAdminOrOwner (K-A); this request only validates the payload.
 */
class UpdateRbacMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint-level authorization lives in the controller (K-A).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cells'                 => ['required', 'array', 'min:1'],
            'cells.*.role'          => ['required', 'string', 'in:' . implode(',', RbacMatrixService::ROLES)],
            'cells.*.menu_key'      => ['required', 'string', 'in:' . implode(',', MenuDefinition::keys())],
            'cells.*.level'         => ['required', 'string', 'in:' . implode(',', RoleMenuAccess::LEVELS)],
        ];
    }

    /**
     * @return array<int, array{role:string, menu_key:string, level:string}>
     */
    public function cells(): array
    {
        return array_map(
            fn (array $cell): array => [
                'role'     => $cell['role'],
                'menu_key' => $cell['menu_key'],
                'level'    => $cell['level'],
            ],
            $this->validated('cells'),
        );
    }

    public function messages(): array
    {
        return [
            'cells.required' => 'At least one cell is required.',
            'cells.min'      => 'At least one cell is required.',
        ];
    }
}
