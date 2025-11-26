<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Users;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\MembergroupName;
use EvolutionCMS\Models\MemberGroup;
use EvolutionCMS\Models\MembergroupAccess;
use EvolutionCMS\Models\User;
use EvolutionCMS\Models\DocumentgroupName;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberGroupController extends ApiController
{
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_users_count' => 'nullable|boolean',
                'include_document_groups_count' => 'nullable|boolean',
            ]);

            $query = MembergroupName::query();

            // Поиск по названию группы
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where('name', 'LIKE', "%{$searchTerm}%");
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeUsersCount = $request->get('include_users_count', false);
            $includeDocGroupsCount = $request->get('include_document_groups_count', false);
            
            // Форматируем данные
            $groups = collect($paginator->items())->map(function($group) use ($includeUsersCount, $includeDocGroupsCount) {
                return $this->formatMemberGroup($group, $includeUsersCount, $includeDocGroupsCount);
            });
            
            return $this->paginated($groups, $paginator, 'Member groups retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch member groups');
        }
    }

    public function show($id)
    {
        try {
            $group = MembergroupName::with(['users', 'documentGroups'])->find($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }
            
            $formattedGroup = $this->formatMemberGroup($group, true, true);
            
            return $this->success($formattedGroup, 'Member group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch member group');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:membergroup_names,name',
                'users' => 'nullable|array',
                'users.*' => 'integer|exists:users,id',
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            // Создаем группу
            $group = MembergroupName::create([
                'name' => $validated['name'],
            ]);

            // Добавляем пользователей в группу
            if (isset($validated['users']) && is_array($validated['users'])) {
                foreach ($validated['users'] as $userId) {
                    MemberGroup::create([
                        'user_group' => $group->id,
                        'member' => $userId,
                    ]);
                }
            }

            // Добавляем доступ к группам документов
            if (isset($validated['document_groups']) && is_array($validated['document_groups'])) {
                foreach ($validated['document_groups'] as $docGroupId) {
                    MembergroupAccess::create([
                        'membergroup' => $group->id,
                        'documentgroup' => $docGroupId,
                    ]);
                }
            }

            $formattedGroup = $this->formatMemberGroup($group->fresh(), true, true);
            
            return $this->created($formattedGroup, 'Member group created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create member group');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $group = MembergroupName::find($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:membergroup_names,name,' . $id,
                'users' => 'nullable|array',
                'users.*' => 'integer|exists:users,id',
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            // Обновляем название группы
            if (isset($validated['name'])) {
                $group->update(['name' => $validated['name']]);
            }

            // Обновляем пользователей (полная синхронизация)
            if (isset($validated['users'])) {
                // Удаляем старых пользователей
                MemberGroup::where('user_group', $id)->delete();
                
                // Добавляем новых пользователей
                foreach ($validated['users'] as $userId) {
                    MemberGroup::create([
                        'user_group' => $id,
                        'member' => $userId,
                    ]);
                }
            }

            // Обновляем доступ к группам документов (полная синхронизация)
            if (isset($validated['document_groups'])) {
                // Удаляем старый доступ
                MembergroupAccess::where('membergroup', $id)->delete();
                
                // Добавляем новый доступ
                foreach ($validated['document_groups'] as $docGroupId) {
                    MembergroupAccess::create([
                        'membergroup' => $id,
                        'documentgroup' => $docGroupId,
                    ]);
                }
            }

            $formattedGroup = $this->formatMemberGroup($group->fresh(), true, true);
            
            return $this->updated($formattedGroup, 'Member group updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update member group');
        }
    }

    public function destroy($id)
    {
        try {
            $group = MembergroupName::find($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            // Удаляем связанные записи
            MemberGroup::where('user_group', $id)->delete();
            MembergroupAccess::where('membergroup', $id)->delete();
            
            $group->delete();

            return $this->deleted('Member group deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete member group');
        }
    }

    public function users($id)
    {
        try {
            $group = MembergroupName::with('users.attributes')->find($id);
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $users = $group->users->map(function($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->attributes->email ?? null,
                    'fullname' => $user->attributes->fullname ?? null,
                    'blocked' => (bool)($user->attributes->blocked ?? false),
                ];
            });

            return $this->success([
                'group_id' => $group->id,
                'group_name' => $group->name,
                'users' => $users,
                'users_count' => $users->count(),
            ], 'Group users retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group users');
        }
    }

    public function addUser(Request $request, $id)
    {
        try {
            $group = MembergroupName::find($id);
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $validated = $this->validateRequest($request, [
                'user_id' => 'required|integer|exists:users,id',
            ]);

            // Проверяем, не добавлен ли уже пользователь
            $existingMember = MemberGroup::where('user_group', $id)
                ->where('member', $validated['user_id'])
                ->first();

            if ($existingMember) {
                return $this->error(
                    'User already in group',
                    ['user' => 'This user is already a member of the group'],
                    422
                );
            }

            // Добавляем пользователя в группу
            MemberGroup::create([
                'user_group' => $id,
                'member' => $validated['user_id'],
            ]);

            $user = User::with('attributes')->find($validated['user_id']);

            return $this->success([
                'group_id' => $group->id,
                'group_name' => $group->name,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->attributes->email ?? null,
                    'fullname' => $user->attributes->fullname ?? null,
                ],
            ], 'User added to group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add user to group');
        }
    }

    public function removeUser($id, $userId)
    {
        try {
            $group = MembergroupName::find($id);
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $member = MemberGroup::where('user_group', $id)
                ->where('member', $userId)
                ->first();

            if (!$member) {
                return $this->notFound('User not found in group');
            }

            $member->delete();

            return $this->deleted('User removed from group successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove user from group');
        }
    }

    public function documentGroups($id)
    {
        try {
            $group = MembergroupName::with('documentGroups')->find($id);
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $documentGroups = $group->documentGroups->map(function($docGroup) {
                return [
                    'id' => $docGroup->id,
                    'name' => $docGroup->name,
                    'private_memgroup' => (bool)$docGroup->private_memgroup,
                    'private_webgroup' => (bool)$docGroup->private_webgroup,
                ];
            });

            return $this->success([
                'group_id' => $group->id,
                'group_name' => $group->name,
                'document_groups' => $documentGroups,
                'document_groups_count' => $documentGroups->count(),
            ], 'Group document access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group document access');
        }
    }

    public function addDocumentGroup(Request $request, $id)
    {
        try {
            $group = MembergroupName::find($id);
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $validated = $this->validateRequest($request, [
                'document_group_id' => 'required|integer|exists:documentgroup_names,id',
            ]);

            // Проверяем, не добавлен ли уже доступ
            $existingAccess = MembergroupAccess::where('membergroup', $id)
                ->where('documentgroup', $validated['document_group_id'])
                ->first();

            if ($existingAccess) {
                return $this->error(
                    'Document group access already exists',
                    ['document_group' => 'This document group is already accessible by the member group'],
                    422
                );
            }

            // Добавляем доступ к группе документов
            MembergroupAccess::create([
                'membergroup' => $id,
                'documentgroup' => $validated['document_group_id'],
            ]);

            $docGroup = DocumentgroupName::find($validated['document_group_id']);

            return $this->success([
                'group_id' => $group->id,
                'group_name' => $group->name,
                'document_group' => [
                    'id' => $docGroup->id,
                    'name' => $docGroup->name,
                    'private_memgroup' => (bool)$docGroup->private_memgroup,
                    'private_webgroup' => (bool)$docGroup->private_webgroup,
                ],
            ], 'Document group access added successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add document group access');
        }
    }

    public function removeDocumentGroup($id, $docGroupId)
    {
        try {
            $group = MembergroupName::find($id);
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $access = MembergroupAccess::where('membergroup', $id)
                ->where('documentgroup', $docGroupId)
                ->first();

            if (!$access) {
                return $this->notFound('Document group access not found');
            }

            $access->delete();

            return $this->deleted('Document group access removed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove document group access');
        }
    }

    public function userGroups($userId)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return $this->notFound('User not found');
            }

            $groups = MemberGroup::where('member', $userId)
                ->with('group')
                ->get()
                ->map(function($memberGroup) {
                    return [
                        'id' => $memberGroup->group->id,
                        'name' => $memberGroup->group->name,
                    ];
                });

            return $this->success([
                'user_id' => $user->id,
                'username' => $user->username,
                'groups' => $groups,
                'groups_count' => $groups->count(),
            ], 'User groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user groups');
        }
    }

    protected function formatMemberGroup($group, $includeUsersCount = false, $includeDocGroupsCount = false)
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
}