<?php

namespace roilafx\Evolutionapi\Services\Users;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\Permissions;
use EvolutionCMS\Models\PermissionsGroups;
use Exception;

class PermissionService extends BaseService
{
    public function getAllGroups(array $params = [])
    {
        $query = PermissionsGroups::query();

        // Поиск по названию группы
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('lang_key', 'LIKE', "%{$searchTerm}%");
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findGroupById(int $id): ?PermissionsGroups
    {
        return PermissionsGroups::with('permissions')->find($id);
    }

    public function createGroup(array $data): PermissionsGroups
    {
        $group = PermissionsGroups::create([
            'name' => $data['name'],
            'lang_key' => $data['lang_key'] ?? '',
        ]);

        // Логируем действие
        $this->logManagerAction('permissions_group_create', $group->id, $group->name);

        return $group;
    }

    public function updateGroup(int $id, array $data): PermissionsGroups
    {
        $group = $this->findGroupById($id);
        if (!$group) {
            throw new Exception('Permission group not found');
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['lang_key'])) {
            $updateData['lang_key'] = $data['lang_key'];
        }

        $group->update($updateData);

        // Логируем действие
        $this->logManagerAction('permissions_group_save', $group->id, $group->name);

        return $group->fresh();
    }

    public function deleteGroup(int $id): bool
    {
        $group = $this->findGroupById($id);
        if (!$group) {
            throw new Exception('Permission group not found');
        }

        // Проверяем, есть ли связанные права
        if ($group->permissions->count() > 0) {
            throw new Exception('Cannot delete permission group with associated permissions');
        }

        // Логируем действие перед удалением
        $this->logManagerAction('permissions_group_delete', $group->id, $group->name);

        $group->delete();

        return true;
    }

    public function getAllPermissions(array $params = [])
    {
        $query = Permissions::query();

        // Поиск по названию или ключу
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('key', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('lang_key', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Фильтр по группе
        if (!empty($params['group_id'])) {
            $query->where('group_id', $params['group_id']);
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findPermissionById(int $id): ?Permissions
    {
        return Permissions::with('attributes')->find($id);
    }

    public function createPermission(array $data): Permissions
    {
        $permission = Permissions::create([
            'name' => $data['name'],
            'key' => $data['key'],
            'lang_key' => $data['lang_key'] ?? '',
            'group_id' => $data['group_id'],
            'disabled' => $data['disabled'] ?? false,
        ]);

        // Логируем действие
        $this->logManagerAction('permission_create', $permission->id, $permission->name);

        return $permission->fresh(['attributes']);
    }

    public function updatePermission(int $id, array $data): Permissions
    {
        $permission = $this->findPermissionById($id);
        if (!$permission) {
            throw new Exception('Permission not found');
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['key'])) {
            $updateData['key'] = $data['key'];
        }
        if (isset($data['lang_key'])) {
            $updateData['lang_key'] = $data['lang_key'];
        }
        if (isset($data['group_id'])) {
            $updateData['group_id'] = $data['group_id'];
        }
        if (isset($data['disabled'])) {
            $updateData['disabled'] = $data['disabled'];
        }

        $permission->update($updateData);

        // Логируем действие
        $this->logManagerAction('permission_save', $permission->id, $permission->name);

        return $permission->fresh(['attributes']);
    }

    public function deletePermission(int $id): bool
    {
        $permission = $this->findPermissionById($id);
        if (!$permission) {
            throw new Exception('Permission not found');
        }

        // Логируем действие перед удалением
        $this->logManagerAction('permission_delete', $permission->id, $permission->name);

        $permission->delete();

        return true;
    }

    public function getGroupPermissions(int $groupId): array
    {
        $group = $this->findGroupById($groupId);
        if (!$group) {
            throw new Exception('Permission group not found');
        }

        return [
            'group' => $group,
            'permissions' => $group->permissions
        ];
    }

    public function movePermissionToGroup(int $permissionId, int $groupId): Permissions
    {
        $permission = $this->findPermissionById($permissionId);
        if (!$permission) {
            throw new Exception('Permission not found');
        }

        $group = $this->findGroupById($groupId);
        if (!$group) {
            throw new Exception('Permission group not found');
        }

        $permission->update([
            'group_id' => $groupId,
        ]);

        // Логируем действие
        $this->logManagerAction('permission_move', $permission->id, $permission->name);

        return $permission->fresh(['attributes']);
    }

    public function enablePermission(int $id): Permissions
    {
        $permission = $this->findPermissionById($id);
        if (!$permission) {
            throw new Exception('Permission not found');
        }

        $permission->update(['disabled' => false]);

        // Логируем действие
        $this->logManagerAction('permission_enable', $permission->id, $permission->name);

        return $permission->fresh(['attributes']);
    }

    public function disablePermission(int $id): Permissions
    {
        $permission = $this->findPermissionById($id);
        if (!$permission) {
            throw new Exception('Permission not found');
        }

        $permission->update(['disabled' => true]);

        // Логируем действие
        $this->logManagerAction('permission_disable', $permission->id, $permission->name);

        return $permission->fresh(['attributes']);
    }

    public function formatPermissionGroup(PermissionsGroups $group, bool $includePermissionsCount = false): array
    {
        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'lang_key' => $group->lang_key,
            'created_at' => $this->safeFormatDate($group->createdon),
            'updated_at' => $this->safeFormatDate($group->editedon),
        ];

        if ($includePermissionsCount) {
            $data['permissions_count'] = $group->permissions->count();
        }

        return $data;
    }

    public function formatPermission(Permissions $permission, bool $includeGroup = false): array
    {
        $data = [
            'id' => $permission->id,
            'name' => $permission->name,
            'key' => $permission->key,
            'lang_key' => $permission->lang_key,
            'disabled' => (bool)$permission->disabled,
            'created_at' => $this->safeFormatDate($permission->createdon),
            'updated_at' => $this->safeFormatDate($permission->editedon),
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

    public function findOrCreateGroupByName(string $name): PermissionsGroups
    {
        $group = PermissionsGroups::firstOrNew(['name' => $name]);
        if (!$group->exists) {
            $group->save();
            $this->logManagerAction('permissions_group_create', $group->id, $group->name);
        }
        return $group;
    }
}