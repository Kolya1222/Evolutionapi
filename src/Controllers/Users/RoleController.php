<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\RoleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends ApiController
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name,description',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_permissions' => 'nullable|boolean',
                'include_tv_access' => 'nullable|boolean',
            ]);

            $paginator = $this->roleService->getAll($validated);
            
            $includePermissions = $request->get('include_permissions', false);
            $includeTvAccess = $request->get('include_tv_access', false);
            
            $roles = collect($paginator->items())->map(function($role) use ($includePermissions, $includeTvAccess) {
                return $this->roleService->formatRole($role, $includePermissions, $includeTvAccess);
            });
            
            return $this->paginated($roles, $paginator, 'Roles retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch roles');
        }
    }

    public function show($id)
    {
        try {
            $role = $this->roleService->findById($id);
                
            if (!$role) {
                return $this->notFound('Role not found');
            }
            
            $formattedRole = $this->roleService->formatRole($role, true, true);
            
            return $this->success($formattedRole, 'Role retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:user_roles,name',
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
                'tv_access' => 'nullable|array',
                'tv_access.*.tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'tv_access.*.rank' => 'nullable|integer|min:0',
            ]);

            $role = $this->roleService->create($validated);
            $formattedRole = $this->roleService->formatRole($role, true, true);
            
            return $this->created($formattedRole, 'Role created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create role');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $role = $this->roleService->findById($id);
                
            if (!$role) {
                return $this->notFound('Role not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:user_roles,name,' . $id,
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
                'tv_access' => 'nullable|array',
                'tv_access.*.tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'tv_access.*.rank' => 'nullable|integer|min:0',
            ]);

            $updatedRole = $this->roleService->update($id, $validated);
            $formattedRole = $this->roleService->formatRole($updatedRole, true, true);
            
            return $this->updated($formattedRole, 'Role updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update role');
        }
    }

    public function destroy($id)
    {
        try {
            $this->roleService->delete($id);

            return $this->deleted('Role deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete role');
        }
    }

    public function permissions($id)
    {
        try {
            $result = $this->roleService->getRolePermissions($id);

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'permissions' => $result['permissions'],
                'permissions_count' => $result['permissions']->count(),
            ], 'Role permissions retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role permissions');
        }
    }

    public function tvAccess($id)
    {
        try {
            $result = $this->roleService->getRoleTvAccess($id);

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'tv_access' => $result['tv_access'],
                'tv_access_count' => $result['tv_access']->count(),
            ], 'Role TV access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role TV access');
        }
    }

    public function addTvAccess(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            $result = $this->roleService->addTvAccessToRole(
                $id, 
                $validated['tmplvarid'], 
                $validated['rank'] ?? 0
            );

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'tv_access' => $this->roleService->formatTvAccess($result),
            ], 'TV access added to role successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add TV access to role');
        }
    }

    public function removeTvAccess($id, $tmplvarid)
    {
        try {
            $this->roleService->removeTvAccessFromRole($id, $tmplvarid);

            return $this->deleted('TV access removed from role successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove TV access from role');
        }
    }

    public function users($id)
    {
        try {
            $result = $this->roleService->getRoleUsers($id);

            $users = collect($result['users'])->map(function($attribute) {
                return $this->roleService->formatUserAttribute($attribute);
            });

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'users' => $users,
                'users_count' => $users->count(),
            ], 'Users with role retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch users with role');
        }
    }
}