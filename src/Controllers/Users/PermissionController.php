<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Users;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\Permissions;
use EvolutionCMS\Models\PermissionsGroups;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PermissionController extends ApiController
{
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

            $query = PermissionsGroups::query();

            // Поиск по названию группы
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('lang_key', 'LIKE', "%{$searchTerm}%");
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includePermissionsCount = $request->get('include_permissions_count', false);
            
            // Форматируем данные
            $groups = collect($paginator->items())->map(function($group) use ($includePermissionsCount) {
                return $this->formatPermissionGroup($group, $includePermissionsCount);
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
            $group = PermissionsGroups::with('permissions')->find($id);
                
            if (!$group) {
                return $this->notFound('Permission group not found');
            }
            
            $formattedGroup = $this->formatPermissionGroup($group, true);
            
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

            $group = PermissionsGroups::create([
                'name' => $validated['name'],
                'lang_key' => $validated['lang_key'] ?? '',
            ]);

            $formattedGroup = $this->formatPermissionGroup($group);
            
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
            $group = PermissionsGroups::find($id);
                
            if (!$group) {
                return $this->notFound('Permission group not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:permissions_groups,name,' . $id,
                'lang_key' => 'nullable|string|max:255',
            ]);

            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['lang_key'])) {
                $updateData['lang_key'] = $validated['lang_key'];
            }

            $group->update($updateData);

            $formattedGroup = $this->formatPermissionGroup($group->fresh());
            
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
            $group = PermissionsGroups::with('permissions')->find($id);
                
            if (!$group) {
                return $this->notFound('Permission group not found');
            }

            // Проверяем, есть ли связанные права
            if ($group->permissions->count() > 0) {
                return $this->error(
                    'Cannot delete permission group with associated permissions', 
                    ['group' => 'Permission group contains permissions. Remove permissions first.'],
                    422
                );
            }

            $group->delete();

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

            $query = Permissions::query();

            // Поиск по названию или ключу
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('key', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('lang_key', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Фильтр по группе
            if ($request->has('group_id')) {
                $query->where('group_id', $validated['group_id']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeGroup = $request->get('include_group', false);
            
            // Форматируем данные
            $permissions = collect($paginator->items())->map(function($permission) use ($includeGroup) {
                return $this->formatPermission($permission, $includeGroup);
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
            $permission = Permissions::with('attributes')->find($id);
                
            if (!$permission) {
                return $this->notFound('Permission not found');
            }
            
            $formattedPermission = $this->formatPermission($permission, true);
            
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

            $permission = Permissions::create([
                'name' => $validated['name'],
                'key' => $validated['key'],
                'lang_key' => $validated['lang_key'] ?? '',
                'group_id' => $validated['group_id'],
                'disabled' => $validated['disabled'] ?? false,
            ]);

            $formattedPermission = $this->formatPermission($permission->fresh(), true);
            
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
            $permission = Permissions::find($id);
                
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

            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['key'])) {
                $updateData['key'] = $validated['key'];
            }
            if (isset($validated['lang_key'])) {
                $updateData['lang_key'] = $validated['lang_key'];
            }
            if (isset($validated['group_id'])) {
                $updateData['group_id'] = $validated['group_id'];
            }
            if (isset($validated['disabled'])) {
                $updateData['disabled'] = $validated['disabled'];
            }

            $permission->update($updateData);

            $formattedPermission = $this->formatPermission($permission->fresh(), true);
            
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
            $permission = Permissions::find($id);
                
            if (!$permission) {
                return $this->notFound('Permission not found');
            }

            $permission->delete();

            return $this->deleted('Permission deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete permission');
        }
    }

    public function groupPermissions($groupId)
    {
        try {
            $group = PermissionsGroups::with('permissions')->find($groupId);
            if (!$group) {
                return $this->notFound('Permission group not found');
            }

            $permissions = $group->permissions->map(function($permission) {
                return $this->formatPermission($permission, false);
            });

            return $this->success([
                'group_id' => $group->id,
                'group_name' => $group->name,
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
            $permission = Permissions::find($id);
            if (!$permission) {
                return $this->notFound('Permission not found');
            }

            $validated = $this->validateRequest($request, [
                'group_id' => 'required|integer|exists:permissions_groups,id',
            ]);

            $permission->update([
                'group_id' => $validated['group_id'],
            ]);

            $formattedPermission = $this->formatPermission($permission->fresh(), true);
            
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
            $permission = Permissions::find($id);
            if (!$permission) {
                return $this->notFound('Permission not found');
            }

            $permission->update(['disabled' => false]);

            return $this->success($this->formatPermission($permission->fresh(), true), 'Permission enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable permission');
        }
    }

    public function disablePermission($id)
    {
        try {
            $permission = Permissions::find($id);
            if (!$permission) {
                return $this->notFound('Permission not found');
            }

            $permission->update(['disabled' => true]);

            return $this->success($this->formatPermission($permission->fresh(), true), 'Permission disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable permission');
        }
    }

    protected function formatPermissionGroup($group, $includePermissionsCount = false)
    {
        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'lang_key' => $group->lang_key,
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
        ];

        if ($includePermissionsCount) {
            $data['permissions_count'] = $group->permissions->count();
        }

        return $data;
    }

    protected function formatPermission($permission, $includeGroup = false)
    {
        $data = [
            'id' => $permission->id,
            'name' => $permission->name,
            'key' => $permission->key,
            'lang_key' => $permission->lang_key,
            'disabled' => (bool)$permission->disabled,
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
        ];

        if ($includeGroup && $permission->attributes) {
            $data['group'] = [
                'id' => $permission->attributes->id,
                'name' => $permission->attributes->name,
                'lang_key' => $permission->attributes->lang_key,
            ];
        }

        return $data;
    }
}