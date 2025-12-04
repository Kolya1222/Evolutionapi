<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\MemberGroupService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Member Groups',
    description: 'Управление группами пользователей (ролями) Evolution CMS'
)]
class MemberGroupController extends ApiController
{
    protected $memberGroupService;

    public function __construct(MemberGroupService $memberGroupService)
    {
        $this->memberGroupService = $memberGroupService;
    }

    #[OA\Get(
        path: '/api/users/member-groups',
        summary: 'Получить список групп пользователей',
        description: 'Возвращает список групп пользователей с пагинацией и фильтрацией',
        tags: ['Member Groups'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name'], default: 'name')
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
                description: 'Поиск по названию группы',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_users_count',
                description: 'Включить количество пользователей в группе (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_document_groups_count',
                description: 'Включить количество групп документов с доступом (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

            $paginator = $this->memberGroupService->getAll($validated);
            
            $includeUsersCount = $request->get('include_users_count', false);
            $includeDocGroupsCount = $request->get('include_document_groups_count', false);
            
            $groups = collect($paginator->items())->map(function($group) use ($includeUsersCount, $includeDocGroupsCount) {
                return $this->memberGroupService->formatMemberGroup($group, $includeUsersCount, $includeDocGroupsCount);
            });
            
            return $this->paginated($groups, $paginator, 'Member groups retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch member groups');
        }
    }

    #[OA\Get(
        path: '/api/users/member-groups/{id}',
        summary: 'Получить информацию о группе пользователей',
        description: 'Возвращает детальную информацию о конкретной группе пользователей',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function show($id)
    {
        try {
            $group = $this->memberGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }
            
            $formattedGroup = $this->memberGroupService->formatMemberGroup($group, true, true);
            
            return $this->success($formattedGroup, 'Member group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch member group');
        }
    }

    #[OA\Post(
        path: '/api/users/member-groups',
        summary: 'Создать новую группу пользователей',
        description: 'Создает новую группу пользователей с указанными параметрами, пользователями и доступом к группам документов',
        tags: ['Member Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Editors'),
                    new OA\Property(
                        property: 'users',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [1, 2, 3])
                    ),
                    new OA\Property(
                        property: 'document_groups',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [1, 2])
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

            $group = $this->memberGroupService->create($validated);
            $formattedGroup = $this->memberGroupService->formatMemberGroup($group, true, true);
            
            return $this->created($formattedGroup, 'Member group created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create member group');
        }
    }

    #[OA\Put(
        path: '/api/users/member-groups/{id}',
        summary: 'Обновить информацию о группе пользователей',
        description: 'Обновляет информацию о существующей группе пользователей',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true, example: 'Senior Editors'),
                    new OA\Property(
                        property: 'users',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [1, 4, 5])
                    ),
                    new OA\Property(
                        property: 'document_groups',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [3, 4])
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $group = $this->memberGroupService->findById($id);
                
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

            $updatedGroup = $this->memberGroupService->update($id, $validated);
            $formattedGroup = $this->memberGroupService->formatMemberGroup($updatedGroup, true, true);
            
            return $this->updated($formattedGroup, 'Member group updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update member group');
        }
    }

    #[OA\Delete(
        path: '/api/users/member-groups/{id}',
        summary: 'Удалить группу пользователей',
        description: 'Удаляет указанную группу пользователей',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function destroy($id)
    {
        try {
            $group = $this->memberGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $this->memberGroupService->delete($id);

            return $this->deleted('Member group deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete member group');
        }
    }

    #[OA\Get(
        path: '/api/users/member-groups/{id}/users',
        summary: 'Получить пользователей группы',
        description: 'Возвращает список пользователей, входящих в указанную группу',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function users($id)
    {
        try {
            $result = $this->memberGroupService->getGroupUsers($id);
            
            $users = collect($result['users'])->map(function($user) {
                return $this->memberGroupService->formatUser($user);
            });

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'users' => $users,
                'users_count' => $users->count(),
            ], 'Group users retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group users');
        }
    }

    #[OA\Post(
        path: '/api/users/member-groups/{id}/users',
        summary: 'Добавить пользователя в группу',
        description: 'Добавляет пользователя в указанную группу',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function addUser(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'user_id' => 'required|integer|exists:users,id',
            ]);

            $result = $this->memberGroupService->addUserToGroup($id, $validated['user_id']);

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'user' => $this->memberGroupService->formatUser($result['user']),
            ], 'User added to group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add user to group');
        }
    }

    #[OA\Delete(
        path: '/api/users/member-groups/{id}/users/{userId}',
        summary: 'Удалить пользователя из группы',
        description: 'Удаляет пользователя из указанной группы',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'userId',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function removeUser($id, $userId)
    {
        try {
            $this->memberGroupService->removeUserFromGroup($id, $userId);

            return $this->deleted('User removed from group successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove user from group');
        }
    }

    #[OA\Get(
        path: '/api/users/member-groups/{id}/document-groups',
        summary: 'Получить доступ к группам документов',
        description: 'Возвращает список групп документов, к которым имеет доступ указанная группа пользователей',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function documentGroups($id)
    {
        try {
            $result = $this->memberGroupService->getGroupDocumentGroups($id);
            
            $documentGroups = collect($result['document_groups'])->map(function($docGroup) {
                return $this->memberGroupService->formatDocumentGroup($docGroup);
            });

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'document_groups' => $documentGroups,
                'document_groups_count' => $documentGroups->count(),
            ], 'Group document access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group document access');
        }
    }

    #[OA\Post(
        path: '/api/users/member-groups/{id}/document-groups',
        summary: 'Добавить доступ к группе документов',
        description: 'Добавляет доступ к группе документов для указанной группы пользователей',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['document_group_id'],
                properties: [
                    new OA\Property(property: 'document_group_id', type: 'integer', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function addDocumentGroup(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'document_group_id' => 'required|integer|exists:documentgroup_names,id',
            ]);

            $result = $this->memberGroupService->addDocumentGroupToGroup($id, $validated['document_group_id']);

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'document_group' => $this->memberGroupService->formatDocumentGroup($result['document_group']),
            ], 'Document group access added successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add document group access');
        }
    }

    #[OA\Delete(
        path: '/api/users/member-groups/{id}/document-groups/{docGroupId}',
        summary: 'Удалить доступ к группе документов',
        description: 'Удаляет доступ к группе документов у указанной группы пользователей',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы пользователей',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'docGroupId',
                description: 'ID группы документов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function removeDocumentGroup($id, $docGroupId)
    {
        try {
            $this->memberGroupService->removeDocumentGroupFromGroup($id, $docGroupId);

            return $this->deleted('Document group access removed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove document group access');
        }
    }

    #[OA\Get(
        path: '/api/users/member-groups/user/{userId}/groups',
        summary: 'Получить группы пользователя',
        description: 'Возвращает список групп, в которые входит указанный пользователь',
        tags: ['Member Groups'],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                description: 'ID пользователя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function userGroups($userId)
    {
        try {
            $result = $this->memberGroupService->getUserGroups($userId);

            $groups = collect($result['groups'])->map(function($memberGroup) {
                return [
                    'id' => $memberGroup->group->id,
                    'name' => $memberGroup->group->name,
                ];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'groups' => $groups,
                'groups_count' => $groups->count(),
            ], 'User groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user groups');
        }
    }
}