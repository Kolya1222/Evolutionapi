<?php

namespace roilafx\Evolutionapi\Services\Users;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\User;
use EvolutionCMS\Models\UserAttribute;
use EvolutionCMS\Models\UserSetting;
use EvolutionCMS\Models\UserValue;
use EvolutionCMS\Models\MemberGroup;
use EvolutionCMS\Models\SiteTmplvar;
use Illuminate\Support\Facades\Hash;
use Exception;

class UserService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = User::query();

        // Поиск по username
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where('username', 'LIKE', "%{$searchTerm}%");
        }

        // Фильтр по статусу блокировки
        if (isset($params['blocked'])) {
            $query->whereHas('attributes', function($q) use ($params) {
                $q->where('blocked', $params['blocked']);
            });
        }

        // Фильтр по верификации
        if (isset($params['verified'])) {
            $query->whereHas('attributes', function($q) use ($params) {
                $q->where('verified', $params['verified']);
            });
        }

        // Фильтр по роли
        if (!empty($params['role'])) {
            $query->whereHas('attributes', function($q) use ($params) {
                $q->where('role', $params['role']);
            });
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'id';
        $sortOrder = $params['sort_order'] ?? 'asc';
        
        if (in_array($sortBy, ['createdon', 'editedon'])) {
            $query->whereHas('attributes')->orderBy(
                UserAttribute::select($sortBy)->whereColumn('user_attributes.internalKey', 'users.id'),
                $sortOrder
            );
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?User
    {
        return User::with(['attributes', 'settings', 'memberGroups', 'values.tmplvar'])->find($id);
    }

    public function create(array $data): User
    {
        // Создаем пользователя
        $userData = [
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'cachepwd' => md5($data['password']), // Для совместимости с EvolutionCMS
        ];

        $user = User::create($userData);

        // Создаем атрибуты пользователя
        $attributeData = [
            'internalKey' => $user->id,
            'fullname' => $data['fullname'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 0,
            'blocked' => $data['blocked'] ?? false,
            'verified' => $data['verified'] ?? true,
            'createdon' => time(),
            'editedon' => time(),
        ];

        // Дополнительные поля атрибутов
        $optionalFields = [
            'phone', 'mobilephone', 'dob', 'gender', 'country', 'street', 
            'city', 'state', 'zip', 'fax', 'photo', 'comment'
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field])) {
                $attributeData[$field] = $data[$field];
            }
        }

        UserAttribute::create($attributeData);

        // Добавляем настройки пользователя
        if (isset($data['settings']) && is_array($data['settings'])) {
            foreach ($data['settings'] as $settingName => $settingValue) {
                UserSetting::create([
                    'user' => $user->id,
                    'setting_name' => $settingName,
                    'setting_value' => $settingValue,
                ]);
            }
        }

        // Добавляем пользователя в группы
        if (isset($data['user_groups']) && is_array($data['user_groups'])) {
            foreach ($data['user_groups'] as $groupId) {
                MemberGroup::create([
                    'user_group' => $groupId,
                    'member' => $user->id,
                ]);
            }
        }

        // Сохраняем TV значения
        if (isset($data['tv_values']) && is_array($data['tv_values'])) {
            $this->saveUserTV($user->id, $data['tv_values']);
        }

        // Логируем действие
        $this->logManagerAction('user_create', $user->id, $user->username);

        return $user->fresh(['attributes', 'settings', 'memberGroups', 'values.tmplvar']);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new Exception('User not found');
        }

        // Обновляем пользователя
        $updateData = [];
        if (isset($data['username'])) {
            $updateData['username'] = $data['username'];
        }
        if (isset($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            $updateData['cachepwd'] = md5($data['password']);
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Обновляем атрибуты
        $attributeUpdate = [];
        $attributeFields = [
            'fullname', 'email', 'role', 'blocked', 'verified', 'phone', 'mobilephone',
            'dob', 'gender', 'country', 'street', 'city', 'state', 'zip', 'fax', 'photo', 'comment'
        ];
        
        foreach ($attributeFields as $field) {
            if (isset($data[$field])) {
                $attributeUpdate[$field] = $data[$field];
            }
        }
        
        $attributeUpdate['editedon'] = time();
        
        if (!empty($attributeUpdate)) {
            UserAttribute::updateOrCreate(
                ['internalKey' => $user->id],
                $attributeUpdate
            );
        }

        // Обновляем настройки
        if (isset($data['settings']) && is_array($data['settings'])) {
            foreach ($data['settings'] as $settingName => $settingValue) {
                UserSetting::updateOrCreate(
                    [
                        'user' => $user->id,
                        'setting_name' => $settingName,
                    ],
                    [
                        'setting_value' => $settingValue,
                    ]
                );
            }
        }

        // Обновляем группы пользователя
        if (isset($data['user_groups'])) {
            // Удаляем текущие группы
            MemberGroup::where('member', $user->id)->delete();
            
            // Добавляем новые группы
            foreach ($data['user_groups'] as $groupId) {
                MemberGroup::create([
                    'user_group' => $groupId,
                    'member' => $user->id,
                ]);
            }
        }

        // Обновляем TV значения
        if (isset($data['tv_values']) && is_array($data['tv_values'])) {
            $this->saveUserTV($user->id, $data['tv_values']);
        }

        // Логируем действие
        $this->logManagerAction('user_save', $user->id, $user->username);

        return $user->fresh(['attributes', 'settings', 'memberGroups', 'values.tmplvar']);
    }

    public function delete(int $id): bool
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new Exception('User not found');
        }

        // Логируем действие перед удалением
        $this->logManagerAction('user_delete', $user->id, $user->username);

        $user->delete();

        return true;
    }

    public function blockUser(int $id): User
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new Exception('User not found');
        }

        UserAttribute::where('internalKey', $user->id)->update([
            'blocked' => true,
            'editedon' => time(),
        ]);

        // Логируем действие
        $this->logManagerAction('user_block', $user->id, $user->username);

        return $user->fresh(['attributes']);
    }

    public function unblockUser(int $id): User
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new Exception('User not found');
        }

        UserAttribute::where('internalKey', $user->id)->update([
            'blocked' => false,
            'editedon' => time(),
        ]);

        // Логируем действие
        $this->logManagerAction('user_unblock', $user->id, $user->username);

        return $user->fresh(['attributes']);
    }

    public function getUserSettings(int $userId): array
    {
        $user = $this->findById($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        return [
            'user' => $user,
            'settings' => $user->settings
        ];
    }

    public function getUserGroups(int $userId): array
    {
        $user = $this->findById($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        return [
            'user' => $user,
            'groups' => $user->memberGroups
        ];
    }

    public function getUserTvValues(int $userId): array
    {
        $user = $this->findById($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        return [
            'user' => $user,
            'tv_values' => $user->values
        ];
    }

    protected function saveUserTV(int $userId, array $tvData): void
    {
        foreach ($tvData as $tvName => $tvValue) {
            $tv = SiteTmplvar::where('name', $tvName)->first();
            
            if ($tv) {
                UserValue::updateOrCreate(
                    [
                        'userid' => $userId,
                        'tmplvarid' => $tv->id,
                    ],
                    [
                        'value' => $tvValue,
                    ]
                );
            }
        }
    }

    public function formatUser(User $user, bool $includeAttributes = false, bool $includeSettings = false, bool $includeGroups = false, bool $includeTV = false): array
    {
        $data = [
            'id' => $user->id,
            'username' => $user->username,
            'created_at' => $this->safeFormatDate($user->attributes->createdon ?? null),
            'updated_at' => $this->safeFormatDate($user->attributes->editedon ?? null),
        ];

        if ($includeAttributes && $user->attributes) {
            $data['attributes'] = [
                'fullname' => $user->attributes->fullname,
                'first_name' => $user->attributes->first_name,
                'middle_name' => $user->attributes->middle_name,
                'last_name' => $user->attributes->last_name,
                'email' => $user->attributes->email,
                'role' => $user->attributes->role,
                'blocked' => (bool)$user->attributes->blocked,
                'verified' => (bool)$user->attributes->verified,
                'phone' => $user->attributes->phone,
                'mobilephone' => $user->attributes->mobilephone,
                'logincount' => $user->attributes->logincount,
                'lastlogin' => $this->safeFormatDate($user->attributes->lastlogin),
                'thislogin' => $this->safeFormatDate($user->attributes->thislogin),
                'failedlogincount' => $user->attributes->failedlogincount,
                'dob' => $this->safeFormatDate($user->attributes->dob),
                'gender' => $user->attributes->gender,
                'country' => $user->attributes->country,
                'street' => $user->attributes->street,
                'city' => $user->attributes->city,
                'state' => $user->attributes->state,
                'zip' => $user->attributes->zip,
                'fax' => $user->attributes->fax,
                'photo' => $user->attributes->photo,
                'comment' => $user->attributes->comment,
            ];
        }

        if ($includeSettings) {
            $data['settings'] = $user->settings->mapWithKeys(function($setting) {
                return [$setting->setting_name => $setting->setting_value];
            });
        }

        if ($includeGroups) {
            $data['groups'] = $user->memberGroups->map(function($memberGroup) {
                return [
                    'id' => $memberGroup->user_group,
                    'name' => $memberGroup->userGroup->name ?? 'Unknown',
                ];
            });
        }

        if ($includeTV) {
            $data['tv_values'] = $user->values->map(function($value) {
                return [
                    'tv_id' => $value->tmplvarid,
                    'tv_name' => $value->tmplvar->name ?? 'Unknown',
                    'tv_caption' => $value->tmplvar->caption ?? '',
                    'value' => $value->value,
                ];
            });
        }

        return $data;
    }
}