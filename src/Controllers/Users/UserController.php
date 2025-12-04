<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Users',
    description: 'Управление пользователями системы'
)]
class UserController extends ApiController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    #[OA\Get(
        path: '/api/users/users',
        summary: 'Список пользователей',
        description: 'Получить список пользователей с пагинацией и фильтрацией',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                description: 'Количество элементов на странице (1-100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)
            ),
            new OA\Parameter(
                name: 'sort_by',
                description: 'Поле для сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['id', 'username', 'createdon', 'editedon'], default: 'id')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Порядок сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Поиск по username',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_attributes',
                description: 'Включить атрибуты пользователя (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_settings',
                description: 'Включить настройки пользователя (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_groups',
                description: 'Включить группы пользователя (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'blocked',
                description: 'Фильтр по статусу блокировки (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'verified',
                description: 'Фильтр по статусу верификации (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'role',
                description: 'Фильтр по ID роли',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
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

            $paginator = $this->userService->getAll($validated);
            
            $includeAttributes = $request->get('include_attributes', false);
            $includeSettings = $request->get('include_settings', false);
            $includeGroups = $request->get('include_groups', false);
            
            $users = collect($paginator->items())->map(function($user) use ($includeAttributes, $includeSettings, $includeGroups) {
                return $this->userService->formatUser($user, $includeAttributes, $includeSettings, $includeGroups);
            });
            
            return $this->paginated($users, $paginator, 'Users retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch users');
        }
    }

    #[OA\Get(
        path: '/api/users/users/{id}',
        summary: 'Получить пользователя',
        description: 'Получить полную информацию о пользователе',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function show($id)
    {
        try {
            $user = $this->userService->findById($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }
            
            $formattedUser = $this->userService->formatUser($user, true, true, true, true);
            
            return $this->success($formattedUser, 'User retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user');
        }
    }

    #[OA\Post(
        path: '/api/users/users',
        summary: 'Создать пользователя',
        description: 'Создать нового пользователя с полным набором данных',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'email', 'fullname'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', maxLength: 255, description: 'Логин пользователя'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6, description: 'Пароль'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Email пользователя'),
                    new OA\Property(property: 'fullname', type: 'string', maxLength: 255, description: 'Полное имя'),
                    new OA\Property(property: 'role', type: 'integer', minimum: 0, description: 'ID роли'),
                    new OA\Property(property: 'blocked', type: 'boolean', description: 'Заблокирован ли пользователь'),
                    new OA\Property(property: 'verified', type: 'boolean', description: 'Верифицирован ли email'),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 20, description: 'Телефон'),
                    new OA\Property(property: 'mobilephone', type: 'string', maxLength: 20, description: 'Мобильный телефон'),
                    new OA\Property(
                        property: 'settings', 
                        type: 'object', 
                        description: 'Настройки пользователя (ключ-значение)',
                        additionalProperties: new OA\AdditionalProperties(type: 'string')
                    ),
                    new OA\Property(
                        property: 'user_groups', 
                        type: 'array', 
                        description: 'Список ID групп пользователя',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'tv_values', 
                        type: 'object', 
                        description: 'TV значения пользователя (имя TV - значение)',
                        additionalProperties: new OA\AdditionalProperties(type: 'string')
                    ),
                    // Дополнительные поля атрибутов
                    new OA\Property(property: 'dob', type: 'string', description: 'Дата рождения (timestamp)'),
                    new OA\Property(property: 'gender', type: 'integer', description: 'Пол (0-не указан, 1-мужской, 2-женский)'),
                    new OA\Property(property: 'country', type: 'string', description: 'Страна'),
                    new OA\Property(property: 'street', type: 'string', description: 'Улица'),
                    new OA\Property(property: 'city', type: 'string', description: 'Город'),
                    new OA\Property(property: 'state', type: 'string', description: 'Штат/Область'),
                    new OA\Property(property: 'zip', type: 'string', description: 'Почтовый индекс'),
                    new OA\Property(property: 'fax', type: 'string', description: 'Факс'),
                    new OA\Property(property: 'photo', type: 'string', description: 'Фото (URL или путь)'),
                    new OA\Property(property: 'comment', type: 'string', description: 'Комментарий'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
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
                'phone' => 'nullable|string|max:20',
                'mobilephone' => 'nullable|string|max:20',
                'settings' => 'nullable|array',
                'user_groups' => 'nullable|array',
                'user_groups.*' => 'integer|exists:membergroup_names,id',
                'tv_values' => 'nullable|array',
            ]);

            $user = $this->userService->create($validated);
            $formattedUser = $this->userService->formatUser($user, true, true, true, true);
            
            return $this->created($formattedUser, 'User created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create user');
        }
    }

    #[OA\Put(
        path: '/api/users/users/{id}',
        summary: 'Обновить пользователя',
        description: 'Обновить информацию о пользователе',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'username', type: 'string', maxLength: 255, description: 'Логин пользователя'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6, description: 'Пароль'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Email пользователя'),
                    new OA\Property(property: 'fullname', type: 'string', maxLength: 255, description: 'Полное имя'),
                    new OA\Property(property: 'role', type: 'integer', minimum: 0, description: 'ID роли'),
                    new OA\Property(property: 'blocked', type: 'boolean', description: 'Заблокирован ли пользователь'),
                    new OA\Property(property: 'verified', type: 'boolean', description: 'Верифицирован ли email'),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 20, description: 'Телефон'),
                    new OA\Property(property: 'mobilephone', type: 'string', maxLength: 20, description: 'Мобильный телефон'),
                    new OA\Property(
                        property: 'settings', 
                        type: 'object', 
                        description: 'Настройки пользователя (полная замена)',
                        additionalProperties: new OA\AdditionalProperties(type: 'string')
                    ),
                    new OA\Property(
                        property: 'user_groups', 
                        type: 'array', 
                        description: 'Список ID групп пользователя (полная замена)',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'tv_values', 
                        type: 'object', 
                        description: 'TV значения пользователя (имя TV - значение)',
                        additionalProperties: new OA\AdditionalProperties(type: 'string')
                    ),
                    // Дополнительные поля атрибутов
                    new OA\Property(property: 'dob', type: 'string', description: 'Дата рождения (timestamp)'),
                    new OA\Property(property: 'gender', type: 'integer', description: 'Пол (0-не указан, 1-мужской, 2-женский)'),
                    new OA\Property(property: 'country', type: 'string', description: 'Страна'),
                    new OA\Property(property: 'street', type: 'string', description: 'Улица'),
                    new OA\Property(property: 'city', type: 'string', description: 'Город'),
                    new OA\Property(property: 'state', type: 'string', description: 'Штат/Область'),
                    new OA\Property(property: 'zip', type: 'string', description: 'Почтовый индекс'),
                    new OA\Property(property: 'fax', type: 'string', description: 'Факс'),
                    new OA\Property(property: 'photo', type: 'string', description: 'Фото (URL или путь)'),
                    new OA\Property(property: 'comment', type: 'string', description: 'Комментарий'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $user = $this->userService->findById($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }

            $validated = $this->validateRequest($request, [
                'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
                'password' => 'nullable|string|min:6',
                'email' => 'sometimes|email|unique:user_attributes,email,' . ($user->attributes->id ?? 0),
                'fullname' => 'sometimes|string|max:255',
                'role' => 'sometimes|integer|min:0',
                'blocked' => 'sometimes|boolean',
                'verified' => 'sometimes|boolean',
                'phone' => 'nullable|string|max:20',
                'mobilephone' => 'nullable|string|max:20',
                'settings' => 'nullable|array',
                'user_groups' => 'nullable|array',
                'user_groups.*' => 'integer|exists:membergroup_names,id',
                'tv_values' => 'nullable|array',
            ]);

            $updatedUser = $this->userService->update($id, $validated);
            $formattedUser = $this->userService->formatUser($updatedUser, true, true, true, true);
            
            return $this->updated($formattedUser, 'User updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update user');
        }
    }

    #[OA\Delete(
        path: '/api/users/users/{id}',
        summary: 'Удалить пользователя',
        description: 'Удалить пользователя из системы',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $this->userService->delete($id);

            return $this->deleted('User deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete user');
        }
    }

    #[OA\Put(
        path: '/api/users/users/{id}/block',
        summary: 'Заблокировать пользователя',
        description: 'Заблокировать доступ пользователя к системе',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function block($id)
    {
        try {
            $user = $this->userService->blockUser($id);
            $formattedUser = $this->userService->formatUser($user, true);
            
            return $this->success($formattedUser, 'User blocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to block user');
        }
    }

    #[OA\Put(
        path: '/api/users/users/{id}/unblock',
        summary: 'Разблокировать пользователя',
        description: 'Разблокировать доступ пользователя к системе',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function unblock($id)
    {
        try {
            $user = $this->userService->unblockUser($id);
            $formattedUser = $this->userService->formatUser($user, true);
            
            return $this->success($formattedUser, 'User unblocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unblock user');
        }
    }

    #[OA\Get(
        path: '/api/users/users/{id}/settings',
        summary: 'Настройки пользователя',
        description: 'Получить настройки конкретного пользователя',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function settings($id)
    {
        try {
            $result = $this->userService->getUserSettings($id);

            $settings = $result['settings']->mapWithKeys(function($setting) {
                return [$setting->setting_name => $setting->setting_value];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'settings' => $settings,
            ], 'User settings retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user settings');
        }
    }

    #[OA\Get(
        path: '/api/users/users/{id}/groups',
        summary: 'Группы пользователя',
        description: 'Получить список групп, в которых состоит пользователь',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function groups($id)
    {
        try {
            $result = $this->userService->getUserGroups($id);

            $groups = $result['groups']->map(function($memberGroup) {
                return [
                    'id' => $memberGroup->user_group,
                    'name' => $memberGroup->userGroup->name ?? 'Unknown',
                ];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'groups' => $groups,
            ], 'User groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user groups');
        }
    }

    #[OA\Get(
        path: '/api/users/users/{id}/tv-values',
        summary: 'TV значения пользователя',
        description: 'Получить TV значения конкретного пользователя',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function tvValues($id)
    {
        try {
            $result = $this->userService->getUserTvValues($id);

            $tvValues = $result['tv_values']->map(function($value) {
                return [
                    'tv_id' => $value->tmplvarid,
                    'tv_name' => $value->tmplvar->name ?? 'Unknown',
                    'tv_caption' => $value->tmplvar->caption ?? '',
                    'value' => $value->value,
                ];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'tv_values' => $tvValues,
            ], 'User TV values retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user TV values');
        }
    }
}