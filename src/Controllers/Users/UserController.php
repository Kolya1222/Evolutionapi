<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Users;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\User;
use EvolutionCMS\Models\UserAttribute;
use EvolutionCMS\Models\UserSetting;
use EvolutionCMS\Models\UserValue;
use EvolutionCMS\Models\MemberGroup;
use EvolutionCMS\Models\SiteTmplvar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,username,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_attributes' => 'nullable|boolean',
                'include_settings' => 'nullable|boolean',
                'include_groups' => 'nullable|boolean',
                'blocked' => 'nullable|boolean',
                'verified' => 'nullable|boolean',
                'role' => 'nullable|integer|min:0',
            ]);

            $query = User::query();

            // Поиск по username
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where('username', 'LIKE', "%{$searchTerm}%");
            }

            // Фильтр по статусу блокировки
            if ($request->has('blocked')) {
                $query->whereHas('attributes', function($q) use ($validated) {
                    $q->where('blocked', $validated['blocked']);
                });
            }

            // Фильтр по верификации
            if ($request->has('verified')) {
                $query->whereHas('attributes', function($q) use ($validated) {
                    $q->where('verified', $validated['verified']);
                });
            }

            // Фильтр по роли
            if ($request->has('role')) {
                $query->whereHas('attributes', function($q) use ($validated) {
                    $q->where('role', $validated['role']);
                });
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'id';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            
            if ($sortBy === 'createdon' || $sortBy === 'editedon') {
                $query->whereHas('attributes')->orderBy(
                    UserAttribute::select($sortBy)->whereColumn('user_attributes.internalKey', 'users.id'),
                    $sortOrder
                );
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeAttributes = $request->get('include_attributes', false);
            $includeSettings = $request->get('include_settings', false);
            $includeGroups = $request->get('include_groups', false);
            
            // Форматируем данные
            $users = collect($paginator->items())->map(function($user) use ($includeAttributes, $includeSettings, $includeGroups) {
                return $this->formatUser($user, $includeAttributes, $includeSettings, $includeGroups);
            });
            
            return $this->paginated($users, $paginator, 'Users retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch users');
        }
    }

    public function show($id)
    {
        try {
            $user = User::with(['attributes', 'settings', 'memberGroups', 'values.tmplvar'])->find($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }
            
            $formattedUser = $this->formatUser($user, true, true, true, true);
            
            return $this->success($formattedUser, 'User retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:6',
                'email' => 'required|email|unique:user_attributes,email',
                'fullname' => 'required|string|max:255',
                'role' => 'nullable|integer|min:0',
                'blocked' => 'nullable|boolean',
                'verified' => 'nullable|boolean',
                'settings' => 'nullable|array',
                'user_groups' => 'nullable|array',
                'user_groups.*' => 'integer|exists:membergroup_names,id',
                'tv_values' => 'nullable|array',
            ]);

            // Создаем пользователя
            $userData = [
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'cachepwd' => md5($validated['password']), // Для совместимости с EvolutionCMS
            ];

            $user = User::create($userData);

            // Создаем атрибуты пользователя
            $attributeData = [
                'internalKey' => $user->id,
                'fullname' => $validated['fullname'],
                'email' => $validated['email'],
                'role' => $validated['role'] ?? 0,
                'blocked' => $validated['blocked'] ?? false,
                'verified' => $validated['verified'] ?? true,
                'createdon' => time(),
                'editedon' => time(),
            ];

            UserAttribute::create($attributeData);

            // Добавляем настройки пользователя
            if (isset($validated['settings']) && is_array($validated['settings'])) {
                foreach ($validated['settings'] as $settingName => $settingValue) {
                    UserSetting::create([
                        'user' => $user->id,
                        'setting_name' => $settingName,
                        'setting_value' => $settingValue,
                    ]);
                }
            }

            // Добавляем пользователя в группы
            if (isset($validated['user_groups']) && is_array($validated['user_groups'])) {
                foreach ($validated['user_groups'] as $groupId) {
                    MemberGroup::create([
                        'user_group' => $groupId,
                        'member' => $user->id,
                    ]);
                }
            }

            // Сохраняем TV значения
            if (isset($validated['tv_values']) && is_array($validated['tv_values'])) {
                $this->saveUserTV($user->id, $validated['tv_values']);
            }

            $formattedUser = $this->formatUser($user->fresh(), true, true, true, true);
            
            return $this->created($formattedUser, 'User created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create user');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }

            $user->load(['attributes', 'settings', 'memberGroups']);
            $attributesId = $user->attributes ? $user->attributes->id : null;
            
            $validated = $this->validateRequest($request, [
                'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
                'password' => 'nullable|string|min:6',
                'email' => 'sometimes|email|unique:user_attributes,email,' . $attributesId,
                'fullname' => 'sometimes|string|max:255',
                'role' => 'sometimes|integer|min:0',
                'blocked' => 'sometimes|boolean',
                'verified' => 'sometimes|boolean',
                'settings' => 'nullable|array',
                'user_groups' => 'nullable|array',
                'user_groups.*' => 'integer|exists:membergroup_names,id',
                'tv_values' => 'nullable|array',
            ]);

            // Обновляем пользователя
            $updateData = [];
            if (isset($validated['username'])) {
                $updateData['username'] = $validated['username'];
            }
            if (isset($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
                $updateData['cachepwd'] = md5($validated['password']);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Обновляем атрибуты
            if ($user->attributes) {
                $attributeUpdate = [];
                $attributeFields = ['fullname', 'email', 'role', 'blocked', 'verified'];
                
                foreach ($attributeFields as $field) {
                    if (isset($validated[$field])) {
                        $attributeUpdate[$field] = $validated[$field];
                    }
                }
                
                $attributeUpdate['editedon'] = time();
                
                if (!empty($attributeUpdate)) {
                    UserAttribute::where('internalKey', $user->id)->update($attributeUpdate);
                }
            }

            // Обновляем настройки
            if (isset($validated['settings']) && is_array($validated['settings'])) {
                foreach ($validated['settings'] as $settingName => $settingValue) {
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
            if (isset($validated['user_groups'])) {
                // Удаляем текущие группы
                MemberGroup::where('member', $user->id)->delete();
                
                // Добавляем новые группы
                foreach ($validated['user_groups'] as $groupId) {
                    MemberGroup::create([
                        'user_group' => $groupId,
                        'member' => $user->id,
                    ]);
                }
            }

            // Обновляем TV значения
            if (isset($validated['tv_values']) && is_array($validated['tv_values'])) {
                $this->saveUserTV($user->id, $validated['tv_values']);
            }

            $formattedUser = $this->formatUser($user->fresh(), true, true, true, true);
            
            return $this->updated($formattedUser, 'User updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update user');
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::find($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }

            $user->delete();

            return $this->deleted('User deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete user');
        }
    }

    public function block($id)
    {
        try {
            $user = User::with('attributes')->find($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }

            if (!$user->attributes) {
                return $this->error('User attributes not found', [], 422);
            }

            UserAttribute::where('internalKey', $user->id)->update([
                'blocked' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatUser($user->fresh(), true), 'User blocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to block user');
        }
    }

    public function unblock($id)
    {
        try {
            $user = User::with('attributes')->find($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }

            if (!$user->attributes) {
                return $this->error('User attributes not found', [], 422);
            }

            UserAttribute::where('internalKey', $user->id)->update([
                'blocked' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatUser($user->fresh(), true), 'User unblocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unblock user');
        }
    }

    public function settings($id)
    {
        try {
            $user = User::with('settings')->find($id);
            if (!$user) {
                return $this->notFound('User not found');
            }

            $settings = $user->settings->mapWithKeys(function($setting) {
                return [$setting->setting_name => $setting->setting_value];
            });

            return $this->success([
                'user_id' => $user->id,
                'username' => $user->username,
                'settings' => $settings,
            ], 'User settings retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user settings');
        }
    }

    public function groups($id)
    {
        try {
            $user = User::with('memberGroups.userGroup')->find($id);
            if (!$user) {
                return $this->notFound('User not found');
            }

            $groups = $user->memberGroups->map(function($memberGroup) {
                return [
                    'id' => $memberGroup->userGroup->id,
                    'name' => $memberGroup->userGroup->name,
                ];
            });

            return $this->success([
                'user_id' => $user->id,
                'username' => $user->username,
                'groups' => $groups,
            ], 'User groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user groups');
        }
    }

    public function tvValues($id)
    {
        try {
            $user = User::with(['values.tmplvar'])->find($id);
            if (!$user) {
                return $this->notFound('User not found');
            }

            $tvValues = $user->values->map(function($value) {
                return [
                    'tv_id' => $value->tmplvarid,
                    'tv_name' => $value->tmplvar->name,
                    'tv_caption' => $value->tmplvar->caption,
                    'value' => $value->value,
                ];
            });

            return $this->success([
                'user_id' => $user->id,
                'username' => $user->username,
                'tv_values' => $tvValues,
            ], 'User TV values retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user TV values');
        }
    }

    protected function formatUser($user, $includeAttributes = false, $includeSettings = false, $includeGroups = false, $includeTV = false)
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
                'city' => $user->attributes->city,
                'state' => $user->attributes->state,
                'zip' => $user->attributes->zip,
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
                    'value' => $value->value,
                ];
            });
        }

        return $data;
    }

    protected function saveUserTV($userId, array $tvData)
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

    protected function safeFormatDate($dateValue)
    {
        if (!$dateValue) return null;
        if ($dateValue instanceof \Illuminate\Support\Carbon) {
            return $dateValue->format('Y-m-d H:i:s');
        }
        if (is_numeric($dateValue) && $dateValue > 0) {
            return date('Y-m-d H:i:s', $dateValue);
        }
        return null;
    }
}