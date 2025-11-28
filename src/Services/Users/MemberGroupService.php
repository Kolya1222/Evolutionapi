<?php

namespace roilafx\Evolutionapi\Services\Users;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\MembergroupName;
use EvolutionCMS\Models\MemberGroup;
use EvolutionCMS\Models\MembergroupAccess;
use EvolutionCMS\Models\User;
use EvolutionCMS\Models\DocumentgroupName;
use Exception;

class MemberGroupService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = MembergroupName::query();

        // Поиск по названию группы
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?MembergroupName
    {
        return MembergroupName::with(['users', 'documentGroups'])->find($id);
    }

    public function create(array $data): MembergroupName
    {
        // Создаем группу
        $group = MembergroupName::create([
            'name' => $data['name'],
        ]);

        // Добавляем пользователей в группу
        if (isset($data['users']) && is_array($data['users'])) {
            foreach ($data['users'] as $userId) {
                MemberGroup::create([
                    'user_group' => $group->id,
                    'member' => $userId,
                ]);
            }
        }

        // Добавляем доступ к группам документов
        if (isset($data['document_groups']) && is_array($data['document_groups'])) {
            foreach ($data['document_groups'] as $docGroupId) {
                MembergroupAccess::create([
                    'membergroup' => $group->id,
                    'documentgroup' => $docGroupId,
                ]);
            }
        }

        // Логируем действие
        $this->logManagerAction('membergroup_create', $group->id, $group->name);

        return $group->fresh(['users', 'documentGroups']);
    }

    public function update(int $id, array $data): MembergroupName
    {
        $group = $this->findById($id);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        // Обновляем название группы
        if (isset($data['name'])) {
            $group->update(['name' => $data['name']]);
        }

        // Обновляем пользователей (полная синхронизация)
        if (isset($data['users'])) {
            // Удаляем старых пользователей
            MemberGroup::where('user_group', $id)->delete();
            
            // Добавляем новых пользователей
            foreach ($data['users'] as $userId) {
                MemberGroup::create([
                    'user_group' => $id,
                    'member' => $userId,
                ]);
            }
        }

        // Обновляем доступ к группам документов (полная синхронизация)
        if (isset($data['document_groups'])) {
            // Удаляем старый доступ
            MembergroupAccess::where('membergroup', $id)->delete();
            
            // Добавляем новый доступ
            foreach ($data['document_groups'] as $docGroupId) {
                MembergroupAccess::create([
                    'membergroup' => $id,
                    'documentgroup' => $docGroupId,
                ]);
            }
        }

        // Логируем действие
        $this->logManagerAction('membergroup_save', $group->id, $group->name);

        return $group->fresh(['users', 'documentGroups']);
    }

    public function delete(int $id): bool
    {
        $group = $this->findById($id);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        // Удаляем связанные записи
        MemberGroup::where('user_group', $id)->delete();
        MembergroupAccess::where('membergroup', $id)->delete();
        
        // Логируем действие перед удалением
        $this->logManagerAction('membergroup_delete', $group->id, $group->name);

        $group->delete();

        return true;
    }

    public function getGroupUsers(int $groupId): array
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        return [
            'group' => $group,
            'users' => $group->users
        ];
    }

    public function addUserToGroup(int $groupId, int $userId): array
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        $user = User::with('attributes')->find($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        // Проверяем, не добавлен ли уже пользователь
        $existingMember = MemberGroup::where('user_group', $groupId)
            ->where('member', $userId)
            ->first();

        if ($existingMember) {
            throw new Exception('User already in group');
        }

        // Добавляем пользователя в группу
        MemberGroup::create([
            'user_group' => $groupId,
            'member' => $userId,
        ]);

        // Логируем действие
        $this->logManagerAction('membergroup_add_user', $groupId, $group->name);

        return [
            'group' => $group,
            'user' => $user
        ];
    }

    public function removeUserFromGroup(int $groupId, int $userId): bool
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        $member = MemberGroup::where('user_group', $groupId)
            ->where('member', $userId)
            ->first();

        if (!$member) {
            throw new Exception('User not found in group');
        }

        // Логируем действие
        $this->logManagerAction('membergroup_remove_user', $groupId, $group->name);

        $member->delete();

        return true;
    }

    public function getGroupDocumentGroups(int $groupId): array
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        return [
            'group' => $group,
            'document_groups' => $group->documentGroups
        ];
    }

    public function addDocumentGroupToGroup(int $groupId, int $documentGroupId): array
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        $documentGroup = DocumentgroupName::find($documentGroupId);
        if (!$documentGroup) {
            throw new Exception('Document group not found');
        }

        // Проверяем, не добавлен ли уже доступ
        $existingAccess = MembergroupAccess::where('membergroup', $groupId)
            ->where('documentgroup', $documentGroupId)
            ->first();

        if ($existingAccess) {
            throw new Exception('Document group access already exists');
        }

        // Добавляем доступ к группе документов
        MembergroupAccess::create([
            'membergroup' => $groupId,
            'documentgroup' => $documentGroupId,
        ]);

        // Логируем действие
        $this->logManagerAction('membergroup_add_documentgroup', $groupId, $group->name);

        return [
            'group' => $group,
            'document_group' => $documentGroup
        ];
    }

    public function removeDocumentGroupFromGroup(int $groupId, int $documentGroupId): bool
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new Exception('Member group not found');
        }

        $access = MembergroupAccess::where('membergroup', $groupId)
            ->where('documentgroup', $documentGroupId)
            ->first();

        if (!$access) {
            throw new Exception('Document group access not found');
        }

        // Логируем действие
        $this->logManagerAction('membergroup_remove_documentgroup', $groupId, $group->name);

        $access->delete();

        return true;
    }

    public function getUserGroups(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        $groups = MemberGroup::where('member', $userId)
            ->with('group')
            ->get();

        return [
            'user' => $user,
            'groups' => $groups
        ];
    }

    public function formatMemberGroup(MembergroupName $group, bool $includeUsersCount = false, bool $includeDocGroupsCount = false): array
    {
        $data = [
            'id' => $group->id,
            'name' => $group->name,
        ];

        if ($includeUsersCount) {
            $data['users_count'] = $group->users->count();
        }

        if ($includeDocGroupsCount) {
            $data['document_groups_count'] = $group->documentGroups->count();
        }

        return $data;
    }

    public function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->attributes->email ?? null,
            'fullname' => $user->attributes->fullname ?? null,
            'blocked' => (bool)($user->attributes->blocked ?? false),
        ];
    }

    public function formatDocumentGroup(DocumentgroupName $documentGroup): array
    {
        return [
            'id' => $documentGroup->id,
            'name' => $documentGroup->name,
            'private_memgroup' => (bool)$documentGroup->private_memgroup,
            'private_webgroup' => (bool)$documentGroup->private_webgroup,
        ];
    }
}