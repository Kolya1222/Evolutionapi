<?php

namespace roilafx\Evolutionapi\Controllers\Content;

use roilafx\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use roilafx\Evolutionapi\Services\Content\DocumentService;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Documents', 
    description: 'Управление документами Evolution CMS'
)]
class DocumentController extends ApiController
{
    private $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    #[OA\Get(
        path: '/api/contents/documents',
        summary: 'Получить список документов',
        description: 'Возвращает список документов с фильтрацией и пагинацией',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'parent',
                description: 'Фильтр по родительскому документу',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'template',
                description: 'Фильтр по шаблону',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'published',
                description: 'Фильтр по статусу публикации',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'deleted',
                description: 'Фильтр по удаленным документам',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Количество элементов на странице (1-100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)
            ),
            new OA\Parameter(
                name: 'include_tv',
                description: 'Включить TV-параметры в ответ',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'], default: 'false')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Поиск по заголовку документа',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'sort_by',
                description: 'Поле для сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['menuindex', 'createdon', 'editedon', 'pagetitle'], default: 'menuindex')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Порядок сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')
            ),
            new OA\Parameter(
                name: 'isfolder',
                description: 'Фильтр по документам-папкам',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'content_type',
                description: 'Фильтр по типу контента',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'group_id',
                description: 'Фильтр по группе документов',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'tv',
                description: 'Фильтр по TV-параметрам',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string')
                )
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
                'parent' => 'nullable|integer|min:0',
                'template' => 'nullable|integer|min:0',
                'published' => 'nullable|boolean',
                'deleted' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
                'include_tv' => 'nullable|boolean',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|string|in:menuindex,createdon,editedon,pagetitle',
                'sort_order' => 'nullable|string|in:asc,desc',
                'isfolder' => 'nullable|boolean',
                'content_type' => 'nullable|string',
                'group_id' => 'nullable|integer|exists:documentgroup_names,id',
                'group_ids' => 'nullable|array',
                'group_ids.*' => 'integer|exists:documentgroup_names,id',
                'tv' => 'nullable|array',
                'tv_filter' => 'nullable|string',
                'tv_order' => 'nullable|string',
            ]);
            
            $filters = array_merge($validated, [
                'without_protected' => true,
            ]);
            
            $result = $this->documentService->searchDocuments($filters);
            
            $includeTV = $request->get('include_tv', false);
            
            $documents = $result->map(function($document) use ($includeTV) {
                return $this->formatDocument($document, $includeTV);
            });
            
            return $this->paginated($documents, $result, 'Documents retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch documents');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/{id}',
        summary: 'Получить документ по ID',
        description: 'Возвращает информацию о документе',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function show($id)
    {
        try {
            $document = $this->documentService->getDocument($id);
            
            $document->load([
                'templateValues.tmplvar',
                'documentGroups',
                'tpl'
            ]);
            
            $formattedDocument = $this->formatDocument($document, true);
            
            return $this->success($formattedDocument, 'Document retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document');
        }
    }

    #[OA\Post(
        path: '/api/contents/documents',
        summary: 'Создать новый документ',
        description: 'Создает новый документ в Evolution CMS',
        tags: ['Documents'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pagetitle', 'parent', 'template'],
                properties: [
                    new OA\Property(property: 'pagetitle', type: 'string', maxLength: 255, example: 'Новый документ', description: 'Заголовок документа'),
                    new OA\Property(property: 'parent', type: 'integer', minimum: 0, example: 0, description: 'ID родительского документа'),
                    new OA\Property(property: 'template', type: 'integer', minimum: 0, example: 1, description: 'ID шаблона'),
                    new OA\Property(property: 'content', type: 'string', example: 'Содержимое документа'),
                    new OA\Property(property: 'alias', type: 'string', maxLength: 255, example: 'novyj-dokument', description: 'Псевдоним (URL)'),
                    new OA\Property(property: 'menuindex', type: 'integer', example: 0, description: 'Позиция в меню'),
                    new OA\Property(property: 'published', type: 'boolean', example: true, description: 'Опубликован'),
                    new OA\Property(property: 'isfolder', type: 'boolean', example: false, description: 'Является папкой'),
                    new OA\Property(property: 'type', type: 'string', enum: ['document', 'reference'], example: 'document', description: 'Тип документа'),
                    new OA\Property(property: 'contentType', type: 'string', example: 'text/html', description: 'Тип контента'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 255, example: 'Описание документа'),
                    new OA\Property(property: 'longtitle', type: 'string', maxLength: 255, example: 'Длинный заголовок'),
                    new OA\Property(property: 'introtext', type: 'string', example: 'Вступительный текст'),
                    new OA\Property(property: 'richtext', type: 'boolean', example: true, description: 'Использовать визуальный редактор'),
                    new OA\Property(property: 'searchable', type: 'boolean', example: true, description: 'Доступен для поиска'),
                    new OA\Property(property: 'cacheable', type: 'boolean', example: true, description: 'Кэшируемый'),
                    new OA\Property(property: 'hidemenu', type: 'boolean', example: false, description: 'Скрыть в меню'),
                    new OA\Property(
                        property: 'tv',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(oneOf: [
                            new OA\Schema(type: 'string'),
                            new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Schema(type: 'number'),
                            new OA\Schema(type: 'boolean'),
                        ]),
                        description: 'TV-параметры документа'
                    ),
                    new OA\Property(
                        property: 'document_groups',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2],
                        description: 'Группы документов'
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
                'pagetitle' => 'required|string|max:255',
                'parent' => 'required|integer|min:0',
                'template' => 'required|integer|min:0',
                'content' => 'nullable|string',
                'alias' => 'nullable|string|max:255|unique:site_content,alias',
                'menuindex' => 'nullable|integer',
                'published' => 'nullable|boolean',
                'isfolder' => 'nullable|boolean',
                'type' => 'nullable|string|in:document,reference',
                'contentType' => 'nullable|string',
                'description' => 'nullable|string|max:255',
                'longtitle' => 'nullable|string|max:255',
                'introtext' => 'nullable|string',
                'richtext' => 'nullable|boolean',
                'searchable' => 'nullable|boolean',
                'cacheable' => 'nullable|boolean',
                'hidemenu' => 'nullable|boolean',
                'tv' => 'nullable|array',
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $document = $this->documentService->createDocument($validated);
            
            if (isset($validated['document_groups'])) {
                $document->documentGroups()->sync($validated['document_groups']);
            }
            
            $formattedDocument = $this->formatDocument($document, true);
            
            return $this->created($formattedDocument, 'Document created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create document');
        }
    }

    #[OA\Put(
        path: '/api/contents/documents/{id}',
        summary: 'Обновить документ',
        description: 'Обновляет информацию о документе',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'pagetitle', type: 'string', maxLength: 255, example: 'Обновленный документ'),
                    new OA\Property(property: 'parent', type: 'integer', minimum: 0, example: 0),
                    new OA\Property(property: 'template', type: 'integer', minimum: 0, example: 1),
                    new OA\Property(property: 'content', type: 'string'),
                    new OA\Property(property: 'alias', type: 'string', maxLength: 255, example: 'obnovlennyj-dokument'),
                    new OA\Property(property: 'menuindex', type: 'integer', example: 1),
                    new OA\Property(property: 'published', type: 'boolean', example: true),
                    new OA\Property(property: 'isfolder', type: 'boolean', example: false),
                    new OA\Property(property: 'type', type: 'string', enum: ['document', 'reference']),
                    new OA\Property(property: 'contentType', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 255),
                    new OA\Property(property: 'longtitle', type: 'string', maxLength: 255),
                    new OA\Property(property: 'introtext', type: 'string'),
                    new OA\Property(property: 'richtext', type: 'boolean', example: true),
                    new OA\Property(property: 'searchable', type: 'boolean', example: true),
                    new OA\Property(property: 'cacheable', type: 'boolean', example: true),
                    new OA\Property(property: 'hidemenu', type: 'boolean', example: false),
                    new OA\Property(
                        property: 'tv',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(oneOf: [
                            new OA\Schema(type: 'string'),
                            new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Schema(type: 'number'),
                            new OA\Schema(type: 'boolean'),
                        ])
                    ),
                    new OA\Property(
                        property: 'document_groups',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2]
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
    public function update(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'pagetitle' => 'sometimes|string|max:255',
                'parent' => 'sometimes|integer|min:0',
                'template' => 'sometimes|integer|min:0',
                'content' => 'nullable|string',
                'alias' => 'nullable|string|max:255|unique:site_content,alias,' . $id,
                'menuindex' => 'nullable|integer',
                'published' => 'nullable|boolean',
                'isfolder' => 'nullable|boolean',
                'type' => 'nullable|string|in:document,reference',
                'contentType' => 'nullable|string',
                'description' => 'nullable|string|max:255',
                'longtitle' => 'nullable|string|max:255',
                'introtext' => 'nullable|string',
                'richtext' => 'nullable|boolean',
                'searchable' => 'nullable|boolean',
                'cacheable' => 'nullable|boolean',
                'hidemenu' => 'nullable|boolean',
                'tv' => 'nullable|array',
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $document = $this->documentService->updateDocument($id, $validated);
            
            // Синхронизируем группы документов если указаны
            if (isset($validated['document_groups'])) {
                $document->documentGroups()->sync($validated['document_groups']);
            }
            
            $formattedDocument = $this->formatDocument($document, true);
            
            return $this->updated($formattedDocument, 'Document updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document');
        }
    }

    #[OA\Delete(
        path: '/api/contents/documents/{id}',
        summary: 'Удалить документ',
        description: 'Удаляет документ (soft delete)',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function destroy($id)
    {
        try {
            $this->documentService->deleteDocument($id);
            return $this->deleted('Document deleted successfully');
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete document');
        }
    }

    #[OA\Put(
        path: '/api/contents/documents/{id}/restore',
        summary: 'Восстановить удаленный документ',
        description: 'Восстанавливает мягко удаленный документ',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID удаленного документа',
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
    public function restore($id)
    {
        try {
            $document = $this->documentService->restoreDocument($id);
            return $this->success($this->formatDocument($document), 'Document restored successfully');
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to restore document');
        }
    }

    #[OA\Post(
        path: '/api/contents/documents/publish-all',
        summary: 'Опубликовать все документы',
        description: 'Публикует все документы, которые должны быть опубликованы по расписанию',
        tags: ['Documents'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function publishAll()
    {
        try {
            $result = $this->documentService->publishDocuments();
            return $this->success($result, "{$result['published_count']} documents published");
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to publish documents');
        }
    }

    #[OA\Post(
        path: '/api/contents/documents/unpublish-all',
        summary: 'Снять с публикации все документы',
        description: 'Снимает с публикации все документы, которые должны быть сняты по расписанию',
        tags: ['Documents'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function unpublishAll()
    {
        try {
            $result = $this->documentService->unpublishDocuments();
            return $this->success($result, "{$result['unpublished_count']} documents unpublished");
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unpublish documents');
        }
    }

    #[OA\Post(
        path: '/api/contents/documents/update-tree',
        summary: 'Обновить дерево документов',
        description: 'Обновляет структуру дерева документов',
        tags: ['Documents'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function updateTree()
    {
        try {
            $result = $this->documentService->updateTree();
            return $this->success($result, 'Document tree updated successfully');
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document tree');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/{id}/children',
        summary: 'Получить дочерние документы',
        description: 'Возвращает дочерние документы указанного документа',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID родительского документа',
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
    public function children($id)
    {
        try {
            $children = $this->documentService->getChildren($id);
            
            $formattedChildren = $children->map(function($document) {
                return $this->formatDocument($document, true);
            });
                
            return $this->success($formattedChildren, 'Document children retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document children');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/{id}/siblings',
        summary: 'Получить соседние документы',
        description: 'Возвращает документы с тем же родителем (братья и сестры)',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function siblings($id)
    {
        try {
            $document = $this->documentService->getDocument($id);
            
            $siblings = $document->siblings()
                ->where('deleted', 0)
                ->orderBy('menuindex', 'asc')
                ->get()
                ->map(function($sibling) {
                    return $this->formatDocument($sibling, true);
                });
                
            return $this->success($siblings, 'Document siblings retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document siblings');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/{id}/ancestors',
        summary: 'Получить предков документа',
        description: 'Возвращает всех предков документа вверх по дереву',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function ancestors($id)
    {
        try {
            $ancestors = $this->documentService->getAncestors($id);
            
            $formattedAncestors = $ancestors->map(function($ancestor) {
                return $this->formatDocument($ancestor, true);
            });
                
            return $this->success($formattedAncestors, 'Document ancestors retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document ancestors');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/{id}/descendants',
        summary: 'Получить потомков документа',
        description: 'Возвращает всех потомков документа вниз по дереву',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function descendants($id)
    {
        try {
            $descendants = $this->documentService->getDescendants($id);
            
            $formattedDescendants = $descendants->map(function($descendant) {
                return $this->formatDocument($descendant, true);
            });
                
            return $this->success($formattedDescendants, 'Document descendants retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document descendants');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/tree',
        summary: 'Получить дерево документов (корневое)',
        description: 'Возвращает дерево документов, начиная с корневого уровня',
        tags: ['Documents'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    #[OA\Get(
        path: '/api/contents/documents/tree/{id}',
        summary: 'Получить дерево документов от указанного узла',
        description: 'Возвращает дерево документов, начиная с указанного документа',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID корневого документа',
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
    public function tree($id = null)
    {
        try {
            $query = SiteContent::where('deleted', 0)
                ->where('published', 1);

            if ($id) {
                $document = $this->documentService->getDocument($id);
                $query = $document->descendantsWithSelf();
            } else {
                $query = $query->where(function($q) {
                    $q->whereNull('parent')->orWhere('parent', 0);
                });
            }

            $documents = $query->get()->map(function($document) {
                return [
                    'id' => $document->id,
                    'title' => $document->pagetitle,
                    'alias' => $document->alias,
                    'parent' => $document->parent,
                    'isfolder' => (bool)$document->isfolder,
                    'menuindex' => $document->menuindex,
                    'published' => (bool)$document->published,
                    'has_children' => $document->hasChildren(),
                    'children_count' => $document->countChildren(),
                    'template' => $document->template,
                    'template_name' => $document->tpl->templatename ?? null,
                ];
            });

            return $this->success($documents, 'Document tree retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document tree');
        }
    }

    #[OA\Put(
        path: '/api/contents/documents/{id}/move',
        summary: 'Переместить документ',
        description: 'Перемещает документ в другое место в дереве',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID перемещаемого документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['new_parent'],
                properties: [
                    new OA\Property(property: 'new_parent', type: 'integer', minimum: 0, example: 1, description: 'ID нового родительского документа'),
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
    public function move(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'new_parent' => 'required|integer|min:0'
            ]);

            $document = $this->documentService->move($id, $validated['new_parent']);
            
            return $this->success($this->formatDocument($document), 'Document moved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move document');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/{id}/groups',
        summary: 'Получить группы документа',
        description: 'Возвращает группы, к которым принадлежит документ',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function groups($id)
    {
        try {
            $document = $this->documentService->getDocument($id);
            $groups = $document->documentGroups()
                ->orderBy('name', 'asc')
                ->get()
                ->map(function($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'type' => $this->getGroupType($group),
                        'private_memgroup' => (bool)$group->private_memgroup,
                        'private_webgroup' => (bool)$group->private_webgroup,
                    ];
                });
                
            return $this->success([
                'document' => $this->formatDocument($document),
                'groups' => $groups,
                'groups_count' => $groups->count(),
            ], 'Document groups retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document groups');
        }
    }


    protected function getGroupType($group)
    {
        if ($group->private_memgroup && $group->private_webgroup) {
            return 'mixed';
        } elseif ($group->private_memgroup) {
            return 'manager';
        } elseif ($group->private_webgroup) {
            return 'web';
        } else {
            return 'public';
        }
    }

    #[OA\Post(
        path: '/api/contents/documents/{id}/groups',
        summary: 'Добавить документ в группы',
        description: 'Добавляет документ в указанные группы',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['group_ids'],
                properties: [
                    new OA\Property(
                        property: 'group_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2],
                        description: 'ID групп для добавления'
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
    public function attachToGroups(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'group_ids' => 'required|array',
                'group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $result = $this->documentService->attachToGroups($id, $validated['group_ids']);

            return $this->success($result, 'Document attached to groups successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to attach document to groups');
        }
    }

    #[OA\Delete(
        path: '/api/contents/documents/{id}/groups/{groupId}',
        summary: 'Удалить документ из группы',
        description: 'Удаляет документ из указанной группы',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'groupId',
                description: 'ID группы',
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
    public function detachFromGroup($id, $groupId)
    {
        try {
            $result = $this->documentService->detachFromGroup($id, $groupId);
            
            if (!$result) {
                return $this->notFound('Group not found for this document');
            }

            return $this->success([
                'detached_group_id' => $groupId,
            ], 'Document detached from group successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to detach document from group');
        }
    }

    #[OA\Post(
        path: '/api/contents/documents/{id}/sync-groups',
        summary: 'Синхронизировать группы документа',
        description: 'Полностью заменяет группы документа на указанные',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['group_ids'],
                properties: [
                    new OA\Property(
                        property: 'group_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2],
                        description: 'ID групп для синхронизации'
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
    public function syncGroups(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'group_ids' => 'required|array',
                'group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $result = $this->documentService->syncGroups($id, $validated['group_ids']);

            return $this->success($result, 'Document groups synced successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to sync document groups');
        }
    }

    #[OA\Get(
        path: '/api/contents/documents/{id}/tv',
        summary: 'Получить TV-параметры документа',
        description: 'Возвращает все TV-параметры документа',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function getTV($id)
    {
        try {
            $tvData = $this->documentService->getDocumentTVFull($id);
            
            return $this->success([
                'document_id' => $id,
                'tv_count' => count($tvData),
                'tv' => $tvData,
            ], 'Document TV parameters retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document TV parameters');
        }
    }

    #[OA\Put(
        path: '/api/contents/documents/{id}/tv',
        summary: 'Обновить TV-параметры документа',
        description: 'Обновляет TV-параметры документа',
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tv'],
                properties: [
                    new OA\Property(
                        property: 'tv',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(oneOf: [
                            new OA\Schema(type: 'string'),
                            new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Schema(type: 'number'),
                            new OA\Schema(type: 'boolean'),
                        ]),
                        example: ['"price": "100"', '"color": "red"'],
                        description: 'TV-параметры для обновления'
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
    public function updateTV(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tv' => 'required|array',
            ]);

            $document = $this->documentService->getDocument($id);
            $this->documentService->saveDocumentTV($document, $validated['tv']);

            return $this->success([
                'document_id' => $id,
                'updated_tv_count' => count($validated['tv']),
            ], 'Document TV parameters updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document TV parameters');
        }
    }

    protected function formatDocument($document, $withTV = false)
    {
        $data = [
            'id' => $document->id,
            'type' => $document->type,
            'content_type' => $document->contentType,
            'title' => $document->pagetitle,
            'long_title' => $document->longtitle,
            'description' => $document->description,
            'alias' => $document->alias,
            'parent' => $document->parent,
            'template' => $document->template,
            'template_name' => $document->tpl->templatename ?? null,
            'menu_index' => $document->menuindex,
            'published' => (bool)$document->published,
            'isfolder' => (bool)$document->isfolder,
            'content' => $document->content,
            'introtext' => $document->introtext,
            'richtext' => (bool)$document->richtext,
            'searchable' => (bool)$document->searchable,
            'cacheable' => (bool)$document->cacheable,
            'hidemenu' => (bool)$document->hidemenu,
            'created_by' => $document->createdby,
            'edited_by' => $document->editedby,
            'published_by' => $document->publishedby,
            'created_at' => $this->documentService->safeFormatDate($document->createdon),
            'updated_at' => $this->documentService->safeFormatDate($document->editedon),
            'published_at' => $this->documentService->safeFormatDate($document->publishedon),
            'publish_date' => $this->documentService->safeFormatDate($document->pub_date),
            'unpublish_date' => $this->documentService->safeFormatDate($document->unpub_date),
            'deleted' => (bool)$document->deleted,
            'deleted_at' => $this->documentService->safeFormatDate($document->deletedon),
            'deleted_by' => $document->deletedby,
            'menutitle' => $document->menutitle,
            'hide_from_tree' => (bool)$document->hide_from_tree,
            'privateweb' => (bool)$document->privateweb,
            'privatemgr' => (bool)$document->privatemgr,
            'content_dispo' => (bool)$document->content_dispo,
            'alias_visible' => (bool)$document->alias_visible,
            //'is_locked' => $document->isAlreadyEdit,
            //'locked_info' => $document->alreadyEditInfo,
        ];
        
        if (!$document->relationLoaded('children')) {
            $data['has_children'] = $document->hasChildren();
            $data['children_count'] = $document->countChildren();
        }
        
        if ($withTV) {
            if ($document->relationLoaded('templateValues')) {
                $data['tv'] = $document->templateValues->mapWithKeys(function($tvValue) {
                    $tv = $tvValue->tmplvar;
                    return [
                        $tv->name => [
                            'value' => $tvValue->value,
                            'tv_id' => $tv->id,
                            'caption' => $tv->caption,
                            'type' => $tv->type,
                        ]
                    ];
                })->toArray();
            } else {
                $data['tv'] = $this->documentService->getDocumentTV($document->id);
            }
        }
        
        return $data;
    }
}