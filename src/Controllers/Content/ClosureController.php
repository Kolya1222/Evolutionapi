<?php

namespace roilafx\Evolutionapi\Controllers\Content;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Content\ClosureService;
use EvolutionCMS\Models\ClosureTable;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Closures', 
    description: 'Управление closure таблицей для иерархии документов'
)]
class ClosureController extends ApiController
{
    private $closureService;

    public function __construct(ClosureService $closureService)
    {
        $this->closureService = $closureService;
    }

    #[OA\Get(
        path: '/api/contents/closures',
        summary: 'Получить список связей closure таблицы',
        description: 'Возвращает список связей между документами с фильтрацией и пагинацией',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                description: 'Количество элементов на странице (1-100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50)
            ),
            new OA\Parameter(
                name: 'sort_by',
                description: 'Поле для сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['closure_id', 'ancestor', 'descendant', 'depth'], default: 'closure_id')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Порядок сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')
            ),
            new OA\Parameter(
                name: 'ancestor',
                description: 'ID документа-предка',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'descendant',
                description: 'ID документа-потомка',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'depth',
                description: 'Точная глубина связи',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'min_depth',
                description: 'Минимальная глубина связи',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'max_depth',
                description: 'Максимальная глубина связи',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'include_ancestor_info',
                description: 'Включить информацию о документе-предке',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'], default: 'false')
            ),
            new OA\Parameter(
                name: 'include_descendant_info',
                description: 'Включить информацию о документе-потомке',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'], default: 'false')
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
                'sort_by' => 'nullable|string|in:closure_id,ancestor,descendant,depth',
                'sort_order' => 'nullable|string|in:asc,desc',
                'ancestor' => 'nullable|integer|exists:site_content,id',
                'descendant' => 'nullable|integer|exists:site_content,id',
                'depth' => 'nullable|integer|min:0',
                'min_depth' => 'nullable|integer|min:0',
                'max_depth' => 'nullable|integer|min:0',
                'include_ancestor_info' => 'nullable|string|in:true,false,1,0',
                'include_descendant_info' => 'nullable|string|in:true,false,1,0',
            ]);

            $query = ClosureTable::query();

            // Фильтр по предку
            if ($request->has('ancestor')) {
                $query->where('ancestor', $validated['ancestor']);
            }

            // Фильтр по потомку
            if ($request->has('descendant')) {
                $query->where('descendant', $validated['descendant']);
            }

            // Фильтр по точной глубине
            if ($request->has('depth')) {
                $query->where('depth', $validated['depth']);
            }

            // Фильтр по минимальной глубине
            if ($request->has('min_depth')) {
                $query->where('depth', '>=', $validated['min_depth']);
            }

            // Фильтр по максимальной глубине
            if ($request->has('max_depth')) {
                $query->where('depth', '<=', $validated['max_depth']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'closure_id';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 50;
            $paginator = $query->paginate($perPage);
            
            $includeAncestorInfo = in_array($validated['include_ancestor_info'] ?? 'false', ['true', '1']);
            $includeDescendantInfo = in_array($validated['include_descendant_info'] ?? 'false', ['true', '1']);
            
            // Форматируем данные
            $closures = collect($paginator->items())->map(function($closure) use ($includeAncestorInfo, $includeDescendantInfo) {
                return $this->formatClosure($closure, $includeAncestorInfo, $includeDescendantInfo);
            });
            
            return $this->paginated($closures, $paginator, 'Closure relationships retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch closure relationships');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/{id}',
        summary: 'Получить связь по ID',
        description: 'Возвращает информацию о связи closure таблицы',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID связи closure таблицы',
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
            $closure = ClosureTable::find($id);
                
            if (!$closure) {
                return $this->notFound('Closure relationship not found');
            }
            
            $formattedClosure = $this->formatClosure($closure, true, true);
            
            return $this->success($formattedClosure, 'Closure relationship retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch closure relationship');
        }
    }

    #[OA\Post(
        path: '/api/contents/closures',
        summary: 'Создать новую связь',
        description: 'Создает новую связь между документами в closure таблице',
        tags: ['Closures'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ancestor', 'descendant'],
                properties: [
                    new OA\Property(property: 'ancestor', type: 'integer', example: 1, description: 'ID документа-предка'),
                    new OA\Property(property: 'descendant', type: 'integer', example: 2, description: 'ID документа-потомка'),
                    new OA\Property(property: 'depth', type: 'integer', minimum: 0, example: 1, description: 'Глубина связи (0 для связи с самим собой)'),
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
                'ancestor' => 'required|integer|exists:site_content,id',
                'descendant' => 'required|integer|exists:site_content,id',
                'depth' => 'nullable|integer|min:0',
            ]);

            // Проверяем, не существует ли уже такая связь
            $existingClosure = ClosureTable::where('ancestor', $validated['ancestor'])
                ->where('descendant', $validated['descendant'])
                ->first();

            if ($existingClosure) {
                return $this->error(
                    'Closure relationship already exists',
                    ['closure' => 'This relationship between ancestor and descendant already exists'],
                    422
                );
            }

            $closureData = [
                'ancestor' => $validated['ancestor'],
                'descendant' => $validated['descendant'],
                'depth' => $validated['depth'] ?? 0,
            ];

            $closure = ClosureTable::create($closureData);

            $formattedClosure = $this->formatClosure($closure, true, true);
            
            return $this->created($formattedClosure, 'Closure relationship created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create closure relationship');
        }
    }

    #[OA\Put(
        path: '/api/contents/closures/{id}',
        summary: 'Обновить связь',
        description: 'Обновляет информацию о связи в closure таблице',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID связи closure таблицы',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'ancestor', type: 'integer', example: 1),
                    new OA\Property(property: 'descendant', type: 'integer', example: 2),
                    new OA\Property(property: 'depth', type: 'integer', minimum: 0, example: 1),
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
            $closure = ClosureTable::find($id);
                
            if (!$closure) {
                return $this->notFound('Closure relationship not found');
            }

            $validated = $this->validateRequest($request, [
                'ancestor' => 'sometimes|integer|exists:site_content,id',
                'descendant' => 'sometimes|integer|exists:site_content,id',
                'depth' => 'nullable|integer|min:0',
            ]);

            $updateData = [];
            $fields = ['ancestor', 'descendant', 'depth'];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            // Проверяем уникальность при обновлении
            if (isset($validated['ancestor']) || isset($validated['descendant'])) {
                $ancestor = $validated['ancestor'] ?? $closure->ancestor;
                $descendant = $validated['descendant'] ?? $closure->descendant;
                
                $existingClosure = ClosureTable::where('ancestor', $ancestor)
                    ->where('descendant', $descendant)
                    ->where('closure_id', '!=', $id)
                    ->first();

                if ($existingClosure) {
                    return $this->error(
                        'Closure relationship already exists',
                        ['closure' => 'This relationship between ancestor and descendant already exists'],
                        422
                    );
                }
            }

            $closure->update($updateData);

            $formattedClosure = $this->formatClosure($closure->fresh(), true, true);
            
            return $this->updated($formattedClosure, 'Closure relationship updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update closure relationship');
        }
    }

    #[OA\Delete(
        path: '/api/contents/closures/{id}',
        summary: 'Удалить связь',
        description: 'Удаляет связь из closure таблицы',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID связи closure таблицы',
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
            $closure = ClosureTable::find($id);
                
            if (!$closure) {
                return $this->notFound('Closure relationship not found');
            }

            $closure->delete();

            return $this->deleted('Closure relationship deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete closure relationship');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/documents/{documentId}/ancestors',
        summary: 'Получить предков документа',
        description: 'Возвращает всех предков указанного документа',
        tags: ['Closures'],
        parameters: [
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
    public function ancestors($documentId)
    {
        try {
            $result = $this->closureService->getAncestors($documentId);
            return $this->success($result, 'Document ancestors retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document ancestors');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/documents/{documentId}/descendants',
        summary: 'Получить потомков документа',
        description: 'Возвращает всех потомков указанного документа',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'max_depth',
                description: 'Максимальная глубина для поиска потомков',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1)
            ),
            new OA\Parameter(
                name: 'include_self',
                description: 'Включить сам документ в результат',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'], default: 'false')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function descendants($documentId, Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'max_depth' => 'nullable|integer|min:1',
                'include_self' => 'nullable|boolean',
            ]);

            $maxDepth = $validated['max_depth'] ?? null;
            $includeSelf = $validated['include_self'] ?? false;

            $result = $this->closureService->getDescendants($documentId, $maxDepth, $includeSelf);
            return $this->success($result, 'Document descendants retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document descendants');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/documents/{documentId}/path',
        summary: 'Получить полный путь документа',
        description: 'Возвращает полный путь документа от корня',
        tags: ['Closures'],
        parameters: [
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
    public function path($documentId)
    {
        try {
            $result = $this->closureService->getPath($documentId);
            return $this->success($result, 'Document path retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document path');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/documents/{documentId}/subtree',
        summary: 'Получить поддерево документа',
        description: 'Возвращает все поддерево документа (все потомки)',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'max_depth',
                description: 'Максимальная глубина поддерева',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1)
            ),
            new OA\Parameter(
                name: 'include_self',
                description: 'Включить сам документ в результат',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'], default: 'false')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function subtree($documentId, Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'max_depth' => 'nullable|integer|min:1',
                'include_self' => 'nullable|boolean',
            ]);

            $maxDepth = $validated['max_depth'] ?? null;
            $includeSelf = $validated['include_self'] ?? false;

            $result = $this->closureService->getSubtree($documentId, $maxDepth, $includeSelf);
            return $this->success($result, 'Document subtree retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document subtree');
        }
    }

    #[OA\Post(
        path: '/api/contents/closures/create-relationship',
        summary: 'Создать связь между документами',
        description: 'Создает новую связь между двумя документами с автоматическим расчетом глубины',
        tags: ['Closures'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ancestor_id', 'descendant_id'],
                properties: [
                    new OA\Property(property: 'ancestor_id', type: 'integer', example: 1, description: 'ID документа-предка'),
                    new OA\Property(property: 'descendant_id', type: 'integer', example: 2, description: 'ID документа-потомка'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function createRelationship(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'ancestor_id' => 'required|integer|exists:site_content,id',
                'descendant_id' => 'required|integer|exists:site_content,id',
            ]);

            $result = $this->closureService->createRelationship(
                $validated['ancestor_id'], 
                $validated['descendant_id']
            );

            return $this->success($result, 'Closure relationship created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create closure relationship');
        }
    }

    #[OA\Put(
        path: '/api/contents/closures/documents/{documentId}/move',
        summary: 'Переместить документ',
        description: 'Перемещает документ в дереве (изменяет родителя)',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID перемещаемого документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'new_ancestor_id', type: 'integer', example: 1, description: 'ID нового родительского документа (null для создания корневого документа)'),
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
    public function moveNode(Request $request, $documentId)
    {
        try {
            $validated = $this->validateRequest($request, [
                'new_ancestor_id' => 'nullable|integer|exists:site_content,id',
            ]);

            $result = $this->closureService->moveNode(
                $documentId, 
                $validated['new_ancestor_id'] ?? null
            );

            return $this->success($result, 'Document moved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move document');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/stats',
        summary: 'Получить статистику closure таблицы',
        description: 'Возвращает статистику и метрики closure таблицы',
        tags: ['Closures'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function stats()
    {
        try {
            $stats = $this->closureService->getStats();
            return $this->success($stats, 'Closure table statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch closure table statistics');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/common-ancestors',
        summary: 'Найти общих предков',
        description: 'Находит общих предков для двух документов',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'document1_id',
                description: 'ID первого документа',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'document2_id',
                description: 'ID второго документа',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function commonAncestors(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'document1_id' => 'required|integer|exists:site_content,id',
                'document2_id' => 'required|integer|exists:site_content,id',
            ]);

            $result = $this->closureService->findCommonAncestors(
                $validated['document1_id'],
                $validated['document2_id']
            );

            return $this->success($result, 'Common ancestors retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch common ancestors');
        }
    }

    #[OA\Get(
        path: '/api/contents/closures/check-ancestry',
        summary: 'Проверить отношение предок-потомок',
        description: 'Проверяет, является ли один документ предком другого',
        tags: ['Closures'],
        parameters: [
            new OA\Parameter(
                name: 'potential_ancestor_id',
                description: 'ID потенциального предка',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'potential_descendant_id',
                description: 'ID потенциального потомка',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function checkAncestry(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'potential_ancestor_id' => 'required|integer|exists:site_content,id',
                'potential_descendant_id' => 'required|integer|exists:site_content,id',
            ]);

            $result = $this->closureService->checkAncestry(
                $validated['potential_ancestor_id'],
                $validated['potential_descendant_id']
            );

            return $this->success($result, 'Ancestry check completed successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to check ancestry');
        }
    }

    protected function formatClosure($closure, $includeAncestorInfo = false, $includeDescendantInfo = false)
    {
        $data = [
            'closure_id' => $closure->closure_id,
            'ancestor' => $closure->ancestor,
            'descendant' => $closure->descendant,
            'depth' => $closure->depth,
        ];

        if ($includeAncestorInfo) {
            $ancestorDoc = SiteContent::find($closure->ancestor);
            $data['ancestor_info'] = $ancestorDoc ? [
                'id' => $ancestorDoc->id,
                'pagetitle' => $ancestorDoc->pagetitle,
                'alias' => $ancestorDoc->alias,
                'published' => (bool)$ancestorDoc->published,
            ] : null;
        }

        if ($includeDescendantInfo) {
            $descendantDoc = SiteContent::find($closure->descendant);
            $data['descendant_info'] = $descendantDoc ? [
                'id' => $descendantDoc->id,
                'pagetitle' => $descendantDoc->pagetitle,
                'alias' => $descendantDoc->alias,
                'published' => (bool)$descendantDoc->published,
            ] : null;
        }

        return $data;
    }

    protected function getMostConnectedDocument()
    {
        $result = ClosureTable::select('ancestor as document_id')
            ->selectRaw('COUNT(*) as connection_count')
            ->where('depth', '>', 0)
            ->groupBy('ancestor')
            ->orderBy('connection_count', 'desc')
            ->first();

        if (!$result) {
            return null;
        }

        $document = SiteContent::find($result->document_id);
        
        return [
            'document_id' => $result->document_id,
            'document_name' => $document ? $document->pagetitle : 'Unknown',
            'connection_count' => $result->connection_count,
        ];
    }
    
    protected function getDeepestPath()
    {
        $result = ClosureTable::select('descendant')
            ->selectRaw('MAX(depth) as max_depth')
            ->groupBy('descendant')
            ->orderBy('max_depth', 'desc')
            ->first();

        if (!$result) {
            return null;
        }

        $document = SiteContent::find($result->descendant);
        $path = ClosureTable::where('descendant', $result->descendant)
            ->orderBy('depth', 'asc')
            ->get()
            ->map(function($closure) {
                $doc = SiteContent::find($closure->ancestor);
                return $doc ? $doc->pagetitle : 'Unknown';
            })
            ->implode(' → ');

        return [
            'document_id' => $result->descendant,
            'document_name' => $document ? $document->pagetitle : 'Unknown',
            'depth' => $result->max_depth,
            'path' => $path,
        ];
    }
}