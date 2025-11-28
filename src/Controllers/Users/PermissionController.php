<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PermissionController extends ApiController
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function groupsIndex(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name,lang_key',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_permissions_count' => 'nullable|boolean',
            ]);

            $paginator = $this->permissionService->getAllGroups($validated);
            
            $includePermissionsCount = $request->get('include_permissions_count', false);
            
            $groups = collect($paginator->items())->map(function($group) use ($includePermissionsCount) {
                return $this->permissionService->formatPermissionGroup($group, $includePermissionsCount);
            });
            
            return $this->paginated($groups, $paginator, 'Permission groups retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permission groups');
        }
    }

    public function groupsShow($id)
    {
        try {
            $group = $this->permissionService->findGroupById($id);
                
            if (!$group) {
                return $this->notFound('Permission group not found');
            }
            
            $formattedGroup = $this->permissionService->formatPermissionGroup($group, true);
            
            return $this->success($formattedGroup, 'Permission group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permission group');
        }
    }

    public function groupsStore(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:permissions_groups,name',
                'lang_key' => 'nullable|string|max:255',
            ]);

            $group = $this->permissionService->createGroup($validated);
            $formattedGroup = $this->permissionService->formatPermissionGroup($group);
            
            return $this->created($formattedGroup, 'Permission group created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create permission group');
        }
    }

    public function groupsUpdate(Request $request, $id)
    {
        try {
            $group = $this->permissionService->findGroupById($id);
                
            if (!$group) {
                return $this->notFound('Permission group not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:permissions_groups,name,' . $id,
                'lang_key' => 'nullable|string|max:255',
            ]);

            $updatedGroup = $this->permissionService->updateGroup($id, $validated);
            $formattedGroup = $this->permissionService->formatPermissionGroup($updatedGroup);
            
            return $this->updated($formattedGroup, 'Permission group updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update permission group');
        }
    }

    public function groupsDestroy($id)
    {
        try {
            $this->permissionService->deleteGroup($id);

            return $this->deleted('Permission group deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete permission group');
        }
    }

    public function permissionsIndex(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name,key,group_id',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'group_id' => 'nullable|integer|exists:permissions_groups,id',
                'include_group' => 'nullable|boolean',
            ]);

            $paginator = $this->permissionService->getAllPermissions($validated);
            
            $includeGroup = $request->get('include_group', false);
            
            $permissions = collect($paginator->items())->map(function($permission) use ($includeGroup) {
                return $this->permissionService->formatPermission($permission, $includeGroup);
            });
            
            return $this->paginated($permissions, $paginator, 'Permissions retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permissions');
        }
    }

    public function permissionsShow($id)
    {
        try {
            $permission = $this->permissionService->findPermissionById($id);
                
            if (!$permission) {
                return $this->notFound('Permission not found');
            }
            
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permission');
        }
    }

    public function permissionsStore(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255',
                'key' => 'required|string|max:255|unique:permissions,key',
                'lang_key' => 'nullable|string|max:255',
                'group_id' => 'required|integer|exists:permissions_groups,id',
                'disabled' => 'nullable|boolean',
            ]);

            $permission = $this->permissionService->createPermission($validated);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->created($formattedPermission, 'Permission created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create permission');
        }
    }

    public function permissionsUpdate(Request $request, $id)
    {
        try {
            $permission = $this->permissionService->findPermissionById($id);
                
            if (!$permission) {
                return $this->notFound('Permission not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255',
                'key' => 'sometimes|string|max:255|unique:permissions,key,' . $id,
                'lang_key' => 'nullable|string|max:255',
                'group_id' => 'sometimes|integer|exists:permissions_groups,id',
                'disabled' => 'nullable|boolean',
            ]);

            $updatedPermission = $this->permissionService->updatePermission($id, $validated);
            $formattedPermission = $this->permissionService->formatPermission($updatedPermission, true);
            
            return $this->updated($formattedPermission, 'Permission updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update permission');
        }
    }

    public function permissionsDestroy($id)
    {
        try {
            $this->permissionService->deletePermission($id);

            return $this->deleted('Permission deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete permission');
        }
    }

    public function groupPermissions($groupId)
    {
        try {
            $result = $this->permissionService->getGroupPermissions($groupId);
            
            $permissions = collect($result['permissions'])->map(function($permission) {
                return $this->permissionService->formatPermission($permission, false);
            });

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'permissions' => $permissions,
                'permissions_count' => $permissions->count(),
            ], 'Group permissions retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group permissions');
        }
    }

    public function movePermission(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'group_id' => 'required|integer|exists:permissions_groups,id',
            ]);

            $permission = $this->permissionService->movePermissionToGroup($id, $validated['group_id']);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission moved to group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move permission');
        }
    }

    public function enablePermission($id)
    {
        try {
            $permission = $this->permissionService->enablePermission($id);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable permission');
        }
    }

    public function disablePermission($id)
    {
        try {
            $permission = $this->permissionService->disablePermission($id);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable permission');
        }
    }
}