<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Users;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\UserRole;
use EvolutionCMS\Models\RolePermissions;
use EvolutionCMS\Models\UserRoleVar;
use EvolutionCMS\Models\SiteTmplvar;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends ApiController
{
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

            $query = UserRole::query();

            // Поиск по названию или описанию
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'id';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includePermissions = $request->get('include_permissions', false);
            $includeTvAccess = $request->get('include_tv_access', false);
            
            // Форматируем данные
            $roles = collect($paginator->items())->map(function($role) use ($includePermissions, $includeTvAccess) {
                return $this->formatRole($role, $includePermissions, $includeTvAccess);
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
            $role = UserRole::with(['tvs', 'roleVar'])->find($id);
                
            if (!$role) {
                return $this->notFound('Role not found');
            }
            
            $formattedRole = $this->formatRole($role, true, true);
            
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

            // Создаем роль
            $roleData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
            ];

            $role = UserRole::create($roleData);

            // Добавляем права
            if (isset($validated['permissions']) && is_array($validated['permissions'])) {
                foreach ($validated['permissions'] as $permission) {
                    RolePermissions::create([
                        'role_id' => $role->id,
                        'permission' => $permission,
                    ]);
                }
            }

            // Добавляем доступ к TV
            if (isset($validated['tv_access']) && is_array($validated['tv_access'])) {
                foreach ($validated['tv_access'] as $tvAccess) {
                    UserRoleVar::create([
                        'roleid' => $role->id,
                        'tmplvarid' => $tvAccess['tmplvarid'],
                        'rank' => $tvAccess['rank'] ?? 0,
                    ]);
                }
            }

            $formattedRole = $this->formatRole($role->fresh(), true, true);
            
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
            $role = UserRole::find($id);
                
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

            // Обновляем роль
            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['description'])) {
                $updateData['description'] = $validated['description'];
            }

            if (!empty($updateData)) {
                $role->update($updateData);
            }

            // Обновляем права (полная синхронизация)
            if (isset($validated['permissions'])) {
                // Удаляем старые права
                RolePermissions::where('role_id', $role->id)->delete();
                
                // Добавляем новые права
                foreach ($validated['permissions'] as $permission) {
                    RolePermissions::create([
                        'role_id' => $role->id,
                        'permission' => $permission,
                    ]);
                }
            }

            // Обновляем доступ к TV (полная синхронизация)
            if (isset($validated['tv_access'])) {
                // Удаляем старый доступ
                UserRoleVar::where('roleid', $role->id)->delete();
                
                // Добавляем новый доступ
                foreach ($validated['tv_access'] as $tvAccess) {
                    UserRoleVar::create([
                        'roleid' => $role->id,
                        'tmplvarid' => $tvAccess['tmplvarid'],
                        'rank' => $tvAccess['rank'] ?? 0,
                    ]);
                }
            }

            $formattedRole = $this->formatRole($role->fresh(), true, true);
            
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
            $role = UserRole::find($id);
                
            if (!$role) {
                return $this->notFound('Role not found');
            }

            // Проверяем, используется ли роль пользователями
            $usersWithRole = \EvolutionCMS\Models\UserAttribute::where('role', $id)->count();
            if ($usersWithRole > 0) {
                return $this->error(
                    'Cannot delete role with assigned users', 
                    ['role' => "Role is assigned to {$usersWithRole} user(s)"],
                    422
                );
            }

            $role->delete();

            return $this->deleted('Role deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete role');
        }
    }

    public function permissions($id)
    {
        try {
            $role = UserRole::find($id);
            if (!$role) {
                return $this->notFound('Role not found');
            }

            $permissions = RolePermissions::where('role_id', $id)
                ->get()
                ->pluck('permission');

            return $this->success([
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions' => $permissions,
                'permissions_count' => $permissions->count(),
            ], 'Role permissions retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role permissions');
        }
    }

    public function tvAccess($id)
    {
        try {
            $role = UserRole::with(['tvs', 'roleVar'])->find($id);
            if (!$role) {
                return $this->notFound('Role not found');
            }

            $tvAccess = $role->tvs->map(function($tv) use ($role) {
                $roleVar = $role->roleVar->where('tmplvarid', $tv->id)->first();
                return [
                    'tv_id' => $tv->id,
                    'tv_name' => $tv->name,
                    'tv_caption' => $tv->caption,
                    'rank' => $roleVar->rank ?? 0,
                    'access_granted' => true,
                ];
            });

            return $this->success([
                'role_id' => $role->id,
                'role_name' => $role->name,
                'tv_access' => $tvAccess,
                'tv_access_count' => $tvAccess->count(),
            ], 'Role TV access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role TV access');
        }
    }

    public function addTvAccess(Request $request, $id)
    {
        try {
            $role = UserRole::find($id);
            if (!$role) {
                return $this->notFound('Role not found');
            }

            $validated = $this->validateRequest($request, [
                'tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            // Проверяем, не добавлен ли уже TV
            $existingAccess = UserRoleVar::where('roleid', $id)
                ->where('tmplvarid', $validated['tmplvarid'])
                ->first();

            if ($existingAccess) {
                return $this->error(
                    'TV access already exists for this role',
                    ['tv_access' => 'This TV is already assigned to the role'],
                    422
                );
            }

            // Добавляем доступ к TV
            UserRoleVar::create([
                'roleid' => $id,
                'tmplvarid' => $validated['tmplvarid'],
                'rank' => $validated['rank'] ?? 0,
            ]);

            $tv = SiteTmplvar::find($validated['tmplvarid']);

            return $this->success([
                'role_id' => $role->id,
                'role_name' => $role->name,
                'tv_access' => [
                    'tv_id' => $tv->id,
                    'tv_name' => $tv->name,
                    'tv_caption' => $tv->caption,
                    'rank' => $validated['rank'] ?? 0,
                ],
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
            $role = UserRole::find($id);
            if (!$role) {
                return $this->notFound('Role not found');
            }

            $tvAccess = UserRoleVar::where('roleid', $id)
                ->where('tmplvarid', $tmplvarid)
                ->first();

            if (!$tvAccess) {
                return $this->notFound('TV access not found for this role');
            }

            $tvAccess->delete();

            return $this->deleted('TV access removed from role successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove TV access from role');
        }
    }

    public function users($id)
    {
        try {
            $role = UserRole::find($id);
            if (!$role) {
                return $this->notFound('Role not found');
            }

            $users = \EvolutionCMS\Models\UserAttribute::where('role', $id)
                ->with('user')
                ->get()
                ->map(function($attribute) {
                    return [
                        'user_id' => $attribute->internalKey,
                        'username' => $attribute->user->username ?? 'Unknown',
                        'fullname' => $attribute->fullname,
                        'email' => $attribute->email,
                        'blocked' => (bool)$attribute->blocked,
                    ];
                });

            return $this->success([
                'role_id' => $role->id,
                'role_name' => $role->name,
                'users' => $users,
                'users_count' => $users->count(),
            ], 'Users with role retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch users with role');
        }
    }

    protected function formatRole($role, $includePermissions = false, $includeTvAccess = false)
    {
        $data = [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_locked' => $role->isAlreadyEdit,
            'locked_info' => $role->alreadyEditInfo,
        ];

        if ($includePermissions) {
            $permissions = RolePermissions::where('role_id', $role->id)
                ->get()
                ->pluck('permission');
            
            $data['permissions'] = $permissions;
            $data['permissions_count'] = $permissions->count();
        }

        if ($includeTvAccess) {
            $tvAccess = $role->tvs->map(function($tv) use ($role) {
                $roleVar = $role->roleVar->where('tmplvarid', $tv->id)->first();
                return [
                    'tv_id' => $tv->id,
                    'tv_name' => $tv->name,
                    'tv_caption' => $tv->caption,
                    'rank' => $roleVar->rank ?? 0,
                ];
            });
            
            $data['tv_access'] = $tvAccess;
            $data['tv_access_count'] = $tvAccess->count();
        }

        return $data;
    }
}