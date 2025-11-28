<?php

namespace roilafx\Evolutionapi\Services\Users;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\UserRole;
use EvolutionCMS\Models\RolePermissions;
use EvolutionCMS\Models\UserRoleVar;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\UserAttribute;
use Exception;

class RoleService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = UserRole::query();

        // Поиск по названию или описанию
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'id';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?UserRole
    {
        return UserRole::with(['tvs', 'roleVar'])->find($id);
    }

    public function create(array $data): UserRole
    {
        // Создаем роль
        $roleData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
        ];

        $role = UserRole::create($roleData);

        // Добавляем права
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            foreach ($data['permissions'] as $permission) {
                RolePermissions::create([
                    'role_id' => $role->id,
                    'permission' => $permission,
                ]);
            }
        }

        // Добавляем доступ к TV
        if (isset($data['tv_access']) && is_array($data['tv_access'])) {
            foreach ($data['tv_access'] as $tvAccess) {
                UserRoleVar::create([
                    'roleid' => $role->id,
                    'tmplvarid' => $tvAccess['tmplvarid'],
                    'rank' => $tvAccess['rank'] ?? 0,
                ]);
            }
        }

        // Логируем действие
        $this->logManagerAction('role_create', $role->id, $role->name);

        return $role->fresh(['tvs', 'roleVar']);
    }

    public function update(int $id, array $data): UserRole
    {
        $role = $this->findById($id);
        if (!$role) {
            throw new Exception('Role not found');
        }

        // Проверяем блокировку
        if ($role->isAlreadyEdit) {
            $lockInfo = $role->alreadyEditInfo;
            throw new Exception(
                "Role is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку перед редактированием
        $this->core->lockElement(8, $id); // 8 - тип роли

        try {
            // Обновляем роль
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (!empty($updateData)) {
                $role->update($updateData);
            }

            // Обновляем права (полная синхронизация)
            if (isset($data['permissions'])) {
                // Удаляем старые права
                RolePermissions::where('role_id', $role->id)->delete();
                
                // Добавляем новые права
                foreach ($data['permissions'] as $permission) {
                    RolePermissions::create([
                        'role_id' => $role->id,
                        'permission' => $permission,
                    ]);
                }
            }

            // Обновляем доступ к TV (полная синхронизация)
            if (isset($data['tv_access'])) {
                // Удаляем старый доступ
                UserRoleVar::where('roleid', $role->id)->delete();
                
                // Добавляем новый доступ
                foreach ($data['tv_access'] as $tvAccess) {
                    UserRoleVar::create([
                        'roleid' => $role->id,
                        'tmplvarid' => $tvAccess['tmplvarid'],
                        'rank' => $tvAccess['rank'] ?? 0,
                    ]);
                }
            }

            // Логируем действие
            $this->logManagerAction('role_save', $role->id, $role->name);

            return $role->fresh(['tvs', 'roleVar']);

        } finally {
            // Снимаем блокировку
            $this->core->unlockElement(8, $id);
        }
    }

    public function delete(int $id): bool
    {
        $role = $this->findById($id);
        if (!$role) {
            throw new Exception('Role not found');
        }

        // Проверяем блокировку
        if ($role->isAlreadyEdit) {
            throw new Exception('Role is locked and cannot be deleted');
        }

        // Проверяем, используется ли роль пользователями
        $usersWithRole = UserAttribute::where('role', $id)->count();
        if ($usersWithRole > 0) {
            throw new Exception("Cannot delete role with {$usersWithRole} assigned users");
        }

        // Логируем действие перед удалением
        $this->logManagerAction('role_delete', $role->id, $role->name);

        $role->delete();

        return true;
    }

    public function getRolePermissions(int $roleId): array
    {
        $role = $this->findById($roleId);
        if (!$role) {
            throw new Exception('Role not found');
        }

        $permissions = RolePermissions::where('role_id', $roleId)
            ->get()
            ->pluck('permission');

        return [
            'role' => $role,
            'permissions' => $permissions
        ];
    }

    public function getRoleTvAccess(int $roleId): array
    {
        $role = $this->findById($roleId);
        if (!$role) {
            throw new Exception('Role not found');
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

        return [
            'role' => $role,
            'tv_access' => $tvAccess
        ];
    }

    public function addTvAccessToRole(int $roleId, int $tmplvarId, int $rank = 0): array
    {
        $role = $this->findById($roleId);
        if (!$role) {
            throw new Exception('Role not found');
        }

        $tv = SiteTmplvar::find($tmplvarId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        // Проверяем, не добавлен ли уже TV
        $existingAccess = UserRoleVar::where('roleid', $roleId)
            ->where('tmplvarid', $tmplvarId)
            ->first();

        if ($existingAccess) {
            throw new Exception('TV access already exists for this role');
        }

        // Добавляем доступ к TV
        UserRoleVar::create([
            'roleid' => $roleId,
            'tmplvarid' => $tmplvarId,
            'rank' => $rank,
        ]);

        // Логируем действие
        $this->logManagerAction('role_add_tv', $role->id, $role->name);

        return [
            'role' => $role,
            'tv' => $tv,
            'rank' => $rank
        ];
    }

    public function removeTvAccessFromRole(int $roleId, int $tmplvarId): bool
    {
        $role = $this->findById($roleId);
        if (!$role) {
            throw new Exception('Role not found');
        }

        $tvAccess = UserRoleVar::where('roleid', $roleId)
            ->where('tmplvarid', $tmplvarId)
            ->first();

        if (!$tvAccess) {
            throw new Exception('TV access not found for this role');
        }

        // Логируем действие
        $this->logManagerAction('role_remove_tv', $role->id, $role->name);

        $tvAccess->delete();

        return true;
    }

    public function getRoleUsers(int $roleId): array
    {
        $role = $this->findById($roleId);
        if (!$role) {
            throw new Exception('Role not found');
        }

        $users = UserAttribute::where('role', $roleId)
            ->with('user')
            ->get();

        return [
            'role' => $role,
            'users' => $users
        ];
    }

    public function formatRole(UserRole $role, bool $includePermissions = false, bool $includeTvAccess = false): array
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

    public function formatUserAttribute(UserAttribute $attribute): array
    {
        return [
            'user_id' => $attribute->internalKey,
            'username' => $attribute->user->username ?? 'Unknown',
            'fullname' => $attribute->fullname,
            'email' => $attribute->email,
            'blocked' => (bool)$attribute->blocked,
        ];
    }

    public function formatTvAccess(array $tvAccess): array
    {
        return [
            'tv_id' => $tvAccess['tv']->id,
            'tv_name' => $tvAccess['tv']->name,
            'tv_caption' => $tvAccess['tv']->caption,
            'rank' => $tvAccess['rank'],
        ];
    }
}