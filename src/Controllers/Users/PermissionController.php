<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Permissions',
    description: 'Управление правами доступа и группами прав'
)]
class PermissionController extends ApiController
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    #[OA\Get(
        path: '/api/users/permissions/groups',
        summary: 'Список групп прав',
        description: 'Получить список групп прав доступа с пагинацией',
        tags: ['Permissions'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'lang_key'], default: 'name')
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
                description: 'Поиск по названию или языковому ключу',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_permissions_count',
                description: 'Включить количество прав в группе (true/false/1/0)',
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

            $paginator = $this->permissionService->getAllGroups($validated);
            
            $includePermissionsCount = $request->get('include_permissions_count', false);
            
            $groups = collect($paginator->items())->map(function($group) use ($includePermissionsCount) {
                return $this->permissionService->formatPermissionGroup($group, $includePermissionsCount);
            });
            
            return $this->paginated($groups, $paginator, 'Permission groups retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permission groups');
        }
    }

    #[OA\Get(
        path: '/api/users/permissions/groups/{id}',
        summary: 'Получить группу прав',
        description: 'Получить информацию о конкретной группе прав доступа',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы прав',
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
    public function groupsShow($id)
    {
        try {
            $group = $this->permissionService->findGroupById($id);
                
            if (!$group) {
                return $this->notFound('Permission group not found');
            }
            
            $formattedGroup = $this->permissionService->formatPermissionGroup($group, true);
            
            return $this->success($formattedGroup, 'Permission group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permission group');
        }
    }

    #[OA\Post(
        path: '/api/users/permissions/groups',
        summary: 'Создать группу прав',
        description: 'Создать новую группу прав доступа',
        tags: ['Permissions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Название группы'),
                    new OA\Property(property: 'lang_key', type: 'string', maxLength: 255, description: 'Языковой ключ'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function groupsStore(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:permissions_groups,name',
                'lang_key' => 'nullable|string|max:255',
            ]);

            $group = $this->permissionService->createGroup($validated);
            $formattedGroup = $this->permissionService->formatPermissionGroup($group);
            
            return $this->created($formattedGroup, 'Permission group created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create permission group');
        }
    }

    #[OA\Put(
        path: '/api/users/permissions/groups/{id}',
        summary: 'Обновить группу прав',
        description: 'Обновить информацию о группе прав доступа',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы прав',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Название группы'),
                    new OA\Property(property: 'lang_key', type: 'string', maxLength: 255, description: 'Языковой ключ'),
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
    public function groupsUpdate(Request $request, $id)
    {
        try {
            $group = $this->permissionService->findGroupById($id);
                
            if (!$group) {
                return $this->notFound('Permission group not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:permissions_groups,name,' . $id,
                'lang_key' => 'nullable|string|max:255',
            ]);

            $updatedGroup = $this->permissionService->updateGroup($id, $validated);
            $formattedGroup = $this->permissionService->formatPermissionGroup($updatedGroup);
            
            return $this->updated($formattedGroup, 'Permission group updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update permission group');
        }
    }

    #[OA\Delete(
        path: '/api/users/permissions/groups/{id}',
        summary: 'Удалить группу прав',
        description: 'Удалить группу прав доступа. Группа должна быть пустой (без привязанных прав).',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы прав',
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
    public function groupsDestroy($id)
    {
        try {
            $this->permissionService->deleteGroup($id);

            return $this->deleted('Permission group deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete permission group');
        }
    }

    #[OA\Get(
        path: '/api/users/permissions',
        summary: 'Список прав доступа',
        description: 'Получить список прав доступа с пагинацией и фильтрацией',
        tags: ['Permissions'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'key', 'group_id'], default: 'name')
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
                description: 'Поиск по названию, ключу или языковому ключу',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'group_id',
                description: 'Фильтр по ID группы прав',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include_group',
                description: 'Включить информацию о группе (true/false/1/0)',
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

            $paginator = $this->permissionService->getAllPermissions($validated);
            
            $includeGroup = $request->get('include_group', false);
            
            $permissions = collect($paginator->items())->map(function($permission) use ($includeGroup) {
                return $this->permissionService->formatPermission($permission, $includeGroup);
            });
            
            return $this->paginated($permissions, $paginator, 'Permissions retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permissions');
        }
    }

    #[OA\Get(
        path: '/api/users/permissions/{id}',
        summary: 'Получить право доступа',
        description: 'Получить информацию о конкретном праве доступа',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID права доступа',
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
    public function permissionsShow($id)
    {
        try {
            $permission = $this->permissionService->findPermissionById($id);
                
            if (!$permission) {
                return $this->notFound('Permission not found');
            }
            
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch permission');
        }
    }

    #[OA\Post(
        path: '/api/users/permissions',
        summary: 'Создать право доступа',
        description: 'Создать новое право доступа',
        tags: ['Permissions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'key', 'group_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Название права'),
                    new OA\Property(property: 'key', type: 'string', maxLength: 255, description: 'Ключ права'),
                    new OA\Property(property: 'lang_key', type: 'string', maxLength: 255, description: 'Языковой ключ'),
                    new OA\Property(property: 'group_id', type: 'integer', description: 'ID группы прав'),
                    new OA\Property(property: 'disabled', type: 'boolean', description: 'Отключено ли право'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
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

            $permission = $this->permissionService->createPermission($validated);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->created($formattedPermission, 'Permission created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create permission');
        }
    }

    #[OA\Put(
        path: '/api/users/permissions/{id}',
        summary: 'Обновить право доступа',
        description: 'Обновить информацию о праве доступа',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID права доступа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, description: 'Название права'),
                    new OA\Property(property: 'key', type: 'string', maxLength: 255, description: 'Ключ права'),
                    new OA\Property(property: 'lang_key', type: 'string', maxLength: 255, description: 'Языковой ключ'),
                    new OA\Property(property: 'group_id', type: 'integer', description: 'ID группы прав'),
                    new OA\Property(property: 'disabled', type: 'boolean', description: 'Отключено ли право'),
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
    public function permissionsUpdate(Request $request, $id)
    {
        try {
            $permission = $this->permissionService->findPermissionById($id);
                
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

            $updatedPermission = $this->permissionService->updatePermission($id, $validated);
            $formattedPermission = $this->permissionService->formatPermission($updatedPermission, true);
            
            return $this->updated($formattedPermission, 'Permission updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update permission');
        }
    }

    #[OA\Delete(
        path: '/api/users/permissions/{id}',
        summary: 'Удалить право доступа',
        description: 'Удалить право доступа',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID права доступа',
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
    public function permissionsDestroy($id)
    {
        try {
            $this->permissionService->deletePermission($id);

            return $this->deleted('Permission deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete permission');
        }
    }

    #[OA\Get(
        path: '/api/users/permissions/groups/{groupId}/permissions',
        summary: 'Права группы',
        description: 'Получить список всех прав доступа в конкретной группе',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'groupId',
                description: 'ID группы прав',
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
    public function groupPermissions($groupId)
    {
        try {
            $result = $this->permissionService->getGroupPermissions($groupId);
            
            $permissions = collect($result['permissions'])->map(function($permission) {
                return $this->permissionService->formatPermission($permission, false);
            });

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'permissions' => $permissions,
                'permissions_count' => $permissions->count(),
            ], 'Group permissions retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group permissions');
        }
    }

    #[OA\Put(
        path: '/api/users/permissions/{id}/move',
        summary: 'Переместить право в другую группу',
        description: 'Переместить право доступа в другую группу прав',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID права доступа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['group_id'],
                properties: [
                    new OA\Property(property: 'group_id', type: 'integer', description: 'ID целевой группы прав'),
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
    public function movePermission(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'group_id' => 'required|integer|exists:permissions_groups,id',
            ]);

            $permission = $this->permissionService->movePermissionToGroup($id, $validated['group_id']);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission moved to group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move permission');
        }
    }

    #[OA\Put(
        path: '/api/users/permissions/{id}/enable',
        summary: 'Включить право доступа',
        description: 'Включить отключенное право доступа',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID права доступа',
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
    public function enablePermission($id)
    {
        try {
            $permission = $this->permissionService->enablePermission($id);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable permission');
        }
    }

    #[OA\Put(
        path: '/api/users/permissions/{id}/disable',
        summary: 'Отключить право доступа',
        description: 'Отключить право доступа',
        tags: ['Permissions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID права доступа',
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
    public function disablePermission($id)
    {
        try {
            $permission = $this->permissionService->disablePermission($id);
            $formattedPermission = $this->permissionService->formatPermission($permission, true);
            
            return $this->success($formattedPermission, 'Permission disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable permission');
        }
    }
}