<?php

namespace roilafx\Evolutionapi\Controllers\Content;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Content\DocumentGroupService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Document Groups', 
    description: 'Управление группами документов'
)]
class DocumentGroupController extends ApiController
{
    protected $documentGroupService;

    public function __construct(DocumentGroupService $documentGroupService)
    {
        $this->documentGroupService = $documentGroupService;
    }

    #[OA\Get(
        path: '/api/contents/document-groups',
        summary: 'Получить список групп документов',
        description: 'Возвращает список групп документов с фильтрацией и пагинацией',
        tags: ['Document Groups'],
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
                name: 'include_counts',
                description: 'Включить количество документов в группе',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'], default: 'false')
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Фильтр по типу группы',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['all', 'web', 'manager', 'mixed', 'public'])
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
                'sort_by' => 'nullable|string|in:id,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_counts' => 'nullable|boolean',
                'type' => 'nullable|string|in:all,web,manager',
            ]);

            $paginator = $this->documentGroupService->getAll($validated);
            
            $includeCounts = $request->get('include_counts', false);
            
            $groups = collect($paginator->items())->map(function($group) use ($includeCounts) {
                return $this->documentGroupService->formatGroup($group, $includeCounts);
            });
            
            return $this->paginated($groups, $paginator, 'Document groups retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document groups');
        }
    }

    #[OA\Get(
        path: '/api/contents/document-groups/{id}',
        summary: 'Получить группу документов по ID',
        description: 'Возвращает информацию о группе документов',
        tags: ['Document Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы документов',
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
            $group = $this->documentGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Document group not found');
            }
            
            $formattedGroup = $this->documentGroupService->formatGroup($group, true);
            
            return $this->success($formattedGroup, 'Document group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document group');
        }
    }

    #[OA\Post(
        path: '/api/contents/document-groups',
        summary: 'Создать новую группу документов',
        description: 'Создает новую группу документов',
        tags: ['Document Groups'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 245, example: 'Администраторы', description: 'Название группы'),
                    new OA\Property(property: 'private_memgroup', type: 'boolean', example: true, description: 'Группа для менеджера'),
                    new OA\Property(property: 'private_webgroup', type: 'boolean', example: false, description: 'Группа для веб-пользователей'),
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
                'name' => 'required|string|max:245|unique:documentgroup_names,name',
                'private_memgroup' => 'nullable|boolean',
                'private_webgroup' => 'nullable|boolean',
            ]);

            // Конвертируем boolean в int для модели
            $groupData = [
                'name' => $validated['name'],
                'private_memgroup' => $validated['private_memgroup'] ?? false ? 1 : 0,
                'private_webgroup' => $validated['private_webgroup'] ?? false ? 1 : 0,
            ];

            $group = $this->documentGroupService->create($groupData);
            
            return $this->created(
                $this->documentGroupService->formatGroup($group), 
                'Document group created successfully'
            );

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create document group');
        }
    }

    #[OA\Put(
        path: '/api/contents/document-groups/{id}',
        summary: 'Обновить группу документов',
        description: 'Обновляет информацию о группе документов',
        tags: ['Document Groups'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы документов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 245, example: 'Обновленное название'),
                    new OA\Property(property: 'private_memgroup', type: 'boolean', example: true),
                    new OA\Property(property: 'private_webgroup', type: 'boolean', example: true),
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
            $group = $this->documentGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:245|unique:documentgroup_names,name,' . $id,
                'private_memgroup' => 'sometimes|boolean',
                'private_webgroup' => 'sometimes|boolean',
            ]);

            // Подготавливаем данные для обновления
            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['private_memgroup'])) {
                $updateData['private_memgroup'] = $validated['private_memgroup'] ? 1 : 0;
            }
            if (isset($validated['private_webgroup'])) {
                $updateData['private_webgroup'] = $validated['private_webgroup'] ? 1 : 0;
            }

            $updatedGroup = $this->documentGroupService->update($id, $updateData);

            if (!$updatedGroup) {
                return $this->notFound('Document group not found');
            }

            return $this->updated(
                $this->documentGroupService->formatGroup($updatedGroup), 
                'Document group updated successfully'
            );

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document group');
        }
    }

    #[OA\Delete(
        path: '/api/contents/document-groups/{id}',
        summary: 'Удалить группу документов',
        description: 'Удаляет группу документов. Группу можно удалить только если в ней нет документов.',
        tags: ['Document Groups'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы документов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $group = $this->documentGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $this->documentGroupService->delete($id);

            return $this->deleted('Document group deleted successfully');

        } catch (\Exception $e) {
            if ($e->getMessage() === 'Cannot delete document group with associated documents') {
                return $this->error(
                    'Cannot delete document group with associated documents', 
                    ['group' => 'Document group contains documents. Remove documents first or use force delete.'],
                    422
                );
            }
            return $this->exceptionError($e, 'Failed to delete document group');
        }
    }

    #[OA\Get(
        path: '/api/contents/document-groups/{id}/documents',
        summary: 'Получить документы в группе',
        description: 'Возвращает все документы, принадлежащие указанной группе',
        tags: ['Document Groups'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы документов',
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
    public function documents($id)
    {
        try {
            $group = $this->documentGroupService->findById($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $documents = $this->documentGroupService->getGroupDocuments($id);
            
            $formattedDocuments = collect($documents)->map(function($document) {
                return $this->documentGroupService->formatDocument($document);
            });
                
            return $this->success([
                'group' => $this->documentGroupService->formatGroup($group),
                'documents' => $formattedDocuments,
                'documents_count' => $formattedDocuments->count(),
            ], 'Documents in group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch documents in group');
        }
    }

    #[OA\Post(
        path: '/api/contents/document-groups/{id}/documents',
        summary: 'Добавить документы в группу',
        description: 'Добавляет документы в указанную группу',
        tags: ['Document Groups'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы документов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['document_ids'],
                properties: [
                    new OA\Property(
                        property: 'document_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'ID документов для добавления'
                    ),
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
    public function attachDocuments(Request $request, $id)
    {
        try {
            $group = $this->documentGroupService->findById($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $validated = $this->validateRequest($request, [
                'document_ids' => 'required|array',
                'document_ids.*' => 'integer|exists:site_content,id',
            ]);

            $result = $this->documentGroupService->attachDocuments($id, $validated['document_ids']);
            
            if ($result['added_count'] === 0) {
                return $this->warning(
                    $this->documentGroupService->formatGroup($group, true),
                    'No new documents to add',
                    ['documents' => 'All provided documents are already in the group']
                );
            }

            $updatedGroup = $this->documentGroupService->findById($id);

            return $this->success([
                'group' => $this->documentGroupService->formatGroup($updatedGroup, true),
                'added_count' => $result['added_count'],
                'added_documents' => $result['added_documents'],
            ], 'Documents attached to group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to attach documents to group');
        }
    }

    #[OA\Delete(
        path: '/api/contents/document-groups/{id}/documents/{documentId}',
        summary: 'Удалить документ из группы',
        description: 'Удаляет документ из указанной группы',
        tags: ['Document Groups'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы документов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
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
    public function detachDocument($id, $documentId)
    {
        try {
            $group = $this->documentGroupService->findById($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $result = $this->documentGroupService->detachDocument($id, $documentId);
            
            if (!$result) {
                return $this->notFound('Document not found in group');
            }

            $updatedGroup = $this->documentGroupService->findById($id);

            return $this->success([
                'group' => $this->documentGroupService->formatGroup($updatedGroup, true),
                'detached_document_id' => $documentId,
            ], 'Document detached from group successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to detach document from group');
        }
    }

    #[OA\Post(
        path: '/api/contents/document-groups/{id}/sync-documents',
        summary: 'Синхронизировать документы в группе',
        description: 'Полностью заменяет документы в группе на указанные',
        tags: ['Document Groups'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID группы документов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['document_ids'],
                properties: [
                    new OA\Property(
                        property: 'document_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'ID документов для синхронизации'
                    ),
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
    public function syncDocuments(Request $request, $id)
    {
        try {
            $group = $this->documentGroupService->findById($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $validated = $this->validateRequest($request, [
                'document_ids' => 'required|array',
                'document_ids.*' => 'integer|exists:site_content,id',
            ]);

            $result = $this->documentGroupService->syncDocuments($id, $validated['document_ids']);

            $updatedGroup = $this->documentGroupService->findById($id);

            return $this->success([
                'group' => $this->documentGroupService->formatGroup($updatedGroup, true),
                'synced_documents' => $result['synced_documents'],
                'documents_count' => $result['documents_count'],
            ], 'Documents synced with group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to sync documents with group');
        }
    }
}