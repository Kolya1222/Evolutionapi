<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\RoleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Roles',
    description: 'Управление ролями пользователей и их правами'
)]
class RoleController extends ApiController
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    #[OA\Get(
        path: '/api/users/roles',
        summary: 'Список ролей',
        description: 'Получить список ролей пользователей с пагинацией',
        tags: ['Roles'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'description'], default: 'id')
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
                description: 'Поиск по названию или описанию',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_permissions',
                description: 'Включить список прав роли (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_tv_access',
                description: 'Включить доступ к TV (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
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
                'sort_by' => 'nullable|string|in:id,name,description',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_permissions' => 'nullable|boolean',
                'include_tv_access' => 'nullable|boolean',
            ]);

            $paginator = $this->roleService->getAll($validated);
            
            $includePermissions = $request->get('include_permissions', false);
            $includeTvAccess = $request->get('include_tv_access', false);
            
            $roles = collect($paginator->items())->map(function($role) use ($includePermissions, $includeTvAccess) {
                return $this->roleService->formatRole($role, $includePermissions, $includeTvAccess);
            });
            
            return $this->paginated($roles, $paginator, 'Roles retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch roles');
        }
    }

    #[OA\Get(
        path: '/api/users/roles/{id}',
        summary: 'Получить роль',
        description: 'Получить информацию о конкретной роли пользователя',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID роли',
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
            $role = $this->roleService->findById($id);
                
            if (!$role) {
                return $this->notFound('Role not found');
            }
            
            $formattedRole = $this->roleService->formatRole($role, true, true);
            
            return $this->success($formattedRole, 'Role retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role');
        }
    }

    #[OA\Post(
        path: '/api/users/roles',
        summary: 'Создать роль',
        description: 'Создать новую роль пользователя с правами и доступом к TV',
        tags: ['Roles'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Название роли'),
                    new OA\Property(property: 'description', type: 'string', description: 'Описание роли'),
                    new OA\Property(
                        property: 'permissions', 
                        type: 'array', 
                        description: 'Список прав роли',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(
                        property: 'tv_access', 
                        type: 'array', 
                        description: 'Список доступов к TV',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'tmplvarid', type: 'integer', description: 'ID TV переменной'),
                                new OA\Property(property: 'rank', type: 'integer', minimum: 0, description: 'Порядковый номер'),
                            ]
                        )
                    ),
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
                'name' => 'required|string|max:255|unique:user_roles,name',
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
                'tv_access' => 'nullable|array',
                'tv_access.*.tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'tv_access.*.rank' => 'nullable|integer|min:0',
            ]);

            $role = $this->roleService->create($validated);
            $formattedRole = $this->roleService->formatRole($role, true, true);
            
            return $this->created($formattedRole, 'Role created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create role');
        }
    }

    #[OA\Put(
        path: '/api/users/roles/{id}',
        summary: 'Обновить роль',
        description: 'Полностью обновить информацию о роли пользователя',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID роли',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Название роли'),
                    new OA\Property(property: 'description', type: 'string', description: 'Описание роли'),
                    new OA\Property(
                        property: 'permissions', 
                        type: 'array', 
                        description: 'Список прав роли (полная замена)',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(
                        property: 'tv_access', 
                        type: 'array', 
                        description: 'Список доступов к TV (полная замена)',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'tmplvarid', type: 'integer', description: 'ID TV переменной'),
                                new OA\Property(property: 'rank', type: 'integer', minimum: 0, description: 'Порядковый номер'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $role = $this->roleService->findById($id);
                
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

            $updatedRole = $this->roleService->update($id, $validated);
            $formattedRole = $this->roleService->formatRole($updatedRole, true, true);
            
            return $this->updated($formattedRole, 'Role updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update role');
        }
    }

    #[OA\Delete(
        path: '/api/users/roles/{id}',
        summary: 'Удалить роль',
        description: 'Удалить роль пользователя. Роль должна быть не заблокирована и не иметь назначенных пользователей.',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID роли',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $this->roleService->delete($id);

            return $this->deleted('Role deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete role');
        }
    }

    #[OA\Get(
        path: '/api/users/roles/{id}/permissions',
        summary: 'Права роли',
        description: 'Получить список прав конкретной роли',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID роли',
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
    public function permissions($id)
    {
        try {
            $result = $this->roleService->getRolePermissions($id);

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'permissions' => $result['permissions'],
                'permissions_count' => $result['permissions']->count(),
            ], 'Role permissions retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role permissions');
        }
    }

    #[OA\Get(
        path: '/api/users/roles/{id}/tv-access',
        summary: 'Доступ к TV роли',
        description: 'Получить список доступов к TV для конкретной роли',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID роли',
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
    public function tvAccess($id)
    {
        try {
            $result = $this->roleService->getRoleTvAccess($id);

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'tv_access' => $result['tv_access'],
                'tv_access_count' => $result['tv_access']->count(),
            ], 'Role TV access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch role TV access');
        }
    }

    #[OA\Post(
        path: '/api/users/roles/{id}/tv-access',
        summary: 'Добавить доступ к TV',
        description: 'Добавить доступ к TV переменной для роли',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID роли',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tmplvarid'],
                properties: [
                    new OA\Property(property: 'tmplvarid', type: 'integer', description: 'ID TV переменной'),
                    new OA\Property(property: 'rank', type: 'integer', minimum: 0, description: 'Порядковый номер'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function addTvAccess(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            $result = $this->roleService->addTvAccessToRole(
                $id, 
                $validated['tmplvarid'], 
                $validated['rank'] ?? 0
            );

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'tv_access' => $this->roleService->formatTvAccess($result),
            ], 'TV access added to role successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add TV access to role');
        }
    }

    #[OA\Delete(
        path: '/api/users/roles/{roleId}/tv-access/{tvId}',
        summary: 'Удалить доступ к TV',
        description: 'Удалить доступ к TV переменной для роли',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'roleId',
                description: 'ID роли',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'tvId',
                description: 'ID TV переменной',
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
    public function removeTvAccess($id, $tmplvarid)
    {
        try {
            $this->roleService->removeTvAccessFromRole($id, $tmplvarid);

            return $this->deleted('TV access removed from role successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove TV access from role');
        }
    }

    #[OA\Get(
        path: '/api/users/roles/{id}/users',
        summary: 'Пользователи с ролью',
        description: 'Получить список пользователей, которым назначена конкретная роль',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID роли',
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
    public function users($id)
    {
        try {
            $result = $this->roleService->getRoleUsers($id);

            $users = collect($result['users'])->map(function($attribute) {
                return $this->roleService->formatUserAttribute($attribute);
            });

            return $this->success([
                'role_id' => $result['role']->id,
                'role_name' => $result['role']->name,
                'users' => $users,
                'users_count' => $users->count(),
            ], 'Users with role retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch users with role');
        }
    }
}