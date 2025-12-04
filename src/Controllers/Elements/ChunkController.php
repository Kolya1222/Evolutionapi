<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\ChunkService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Сhunks',
    description: 'Управление элементами - чанками'
)]
class ChunkController extends ApiController
{
    protected $chunkService;

    public function __construct(ChunkService $chunkService)
    {
        $this->chunkService = $chunkService;
    }

    #[OA\Get(
        path: '/api/elements/chunks',
        summary: 'Получить список чанков',
        description: 'Возвращает список чанков с пагинацией и фильтрацией',
        tags: ['Сhunks'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'createdon', 'editedon'], default: 'name')
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
                name: 'category',
                description: 'ID категории для фильтрации',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'locked',
                description: 'Фильтр по блокировке (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'disabled',
                description: 'Фильтр по отключению (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'cache_type',
                description: 'Фильтр по типу кэширования (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_category',
                description: 'Включить информацию о категории (true/false/1/0)',
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
                'sort_by' => 'nullable|string|in:id,name,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'cache_type' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
            ]);

            $paginator = $this->chunkService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            
            $chunks = collect($paginator->items())->map(function($chunk) use ($includeCategory) {
                return $this->chunkService->formatChunk($chunk, $includeCategory);
            });
            
            return $this->paginated($chunks, $paginator, 'Chunks retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch chunks');
        }
    }

    #[OA\Get(
        path: '/api/elements/chunks/{id}',
        summary: 'Получить информацию о чанке',
        description: 'Возвращает детальную информацию о конкретном чанке',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
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
            $chunk = $this->chunkService->findById($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }
            
            $formattedChunk = $this->chunkService->formatChunk($chunk, true);
            
            return $this->success($formattedChunk, 'Chunk retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch chunk');
        }
    }

    #[OA\Post(
        path: '/api/elements/chunks',
        summary: 'Создать новый чанк',
        description: 'Создает новый чанк с указанными параметрами',
        tags: ['Сhunks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'snippet'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'MyChunk'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Описание чанка'),
                    new OA\Property(property: 'snippet', type: 'string', example: '[[+param1]] [[+param2]]'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'editor_name', type: 'string', maxLength: 255, nullable: true, example: ''),
                    new OA\Property(property: 'cache_type', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false)
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
                'name' => 'required|string|max:255|unique:site_htmlsnippets,name',
                'description' => 'nullable|string',
                'snippet' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'editor_name' => 'nullable|string|max:255',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
            ]);

            $chunk = $this->chunkService->create($validated);
            $formattedChunk = $this->chunkService->formatChunk($chunk, true);
            
            return $this->created($formattedChunk, 'Chunk created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create chunk');
        }
    }

    #[OA\Put(
        path: '/api/elements/chunks/{id}',
        summary: 'Обновить информацию о чанке',
        description: 'Обновляет информацию о существующем чанке',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true, example: 'UpdatedChunk'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Обновленное описание'),
                    new OA\Property(property: 'snippet', type: 'string', nullable: true, example: '[[+updated_param]]'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'editor_name', type: 'string', maxLength: 255, nullable: true, example: 'Ace'),
                    new OA\Property(property: 'cache_type', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false)
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
            $chunk = $this->chunkService->findById($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_htmlsnippets,name,' . $id,
                'description' => 'nullable|string',
                'snippet' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'editor_name' => 'nullable|string|max:255',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
            ]);

            $updatedChunk = $this->chunkService->update($id, $validated);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->updated($formattedChunk, 'Chunk updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update chunk');
        }
    }

    #[OA\Delete(
        path: '/api/elements/chunks/{id}',
        summary: 'Удалить чанк',
        description: 'Удаляет указанный чанк',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
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
            $chunk = $this->chunkService->findById($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $this->chunkService->delete($id);

            return $this->deleted('Chunk deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete chunk');
        }
    }

    #[OA\Post(
        path: '/api/elements/chunks/{id}/duplicate',
        summary: 'Дублировать чанк',
        description: 'Создает копию существующего чанка',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка для копирования',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function duplicate($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $newChunk = $this->chunkService->duplicate($id);
            $formattedChunk = $this->chunkService->formatChunk($newChunk, true);
            
            return $this->created($formattedChunk, 'Chunk duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate chunk');
        }
    }

    #[OA\Post(
        path: '/api/elements/chunks/{id}/enable',
        summary: 'Включить чанк',
        description: 'Включает отключенный чанк',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
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
    public function enable($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'disabled', false);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable chunk');
        }
    }

    #[OA\Post(
        path: '/api/elements/chunks/{id}/disable',
        summary: 'Отключить чанк',
        description: 'Отключает чанк',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
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
    public function disable($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'disabled', true);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable chunk');
        }
    }

    #[OA\Post(
        path: '/api/elements/chunks/{id}/lock',
        summary: 'Заблокировать чанк',
        description: 'Блокирует чанк от редактирования',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
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
    public function lock($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'locked', true);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock chunk');
        }
    }

    #[OA\Post(
        path: '/api/elements/chunks/{id}/unlock',
        summary: 'Разблокировать чанк',
        description: 'Разблокирует чанк для редактирования',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
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
    public function unlock($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'locked', false);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock chunk');
        }
    }

    #[OA\Get(
        path: '/api/elements/chunks/{id}/content',
        summary: 'Получить содержимое чанка',
        description: 'Возвращает только содержимое (код) чанка',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
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
    public function content($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            return $this->success([
                'chunk_id' => $chunk->id,
                'chunk_name' => $chunk->name,
                'content' => $chunk->snippet,
            ], 'Chunk content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch chunk content');
        }
    }

    #[OA\Put(
        path: '/api/elements/chunks/{id}/content',
        summary: 'Обновить содержимое чанка',
        description: 'Обновляет только содержимое (код) чанка',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: '[[+new_param]] New content')
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
    public function updateContent(Request $request, $id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $updatedChunk = $this->chunkService->updateContent($id, $validated['content']);

            return $this->success([
                'chunk_id' => $updatedChunk->id,
                'chunk_name' => $updatedChunk->name,
                'content' => $updatedChunk->snippet,
            ], 'Chunk content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update chunk content');
        }
    }

    #[OA\Post(
        path: '/api/elements/chunks/{id}/execute',
        summary: 'Выполнить чанк',
        description: 'Выполняет код чанка с указанными параметрами и возвращает результат',
        tags: ['Сhunks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID чанка',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'param1', type: 'string', example: 'value1'),
                    new OA\Property(property: 'param2', type: 'string', example: 'value2')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 423, ref: '#/components/responses/409'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function execute($id, Request $request)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            if ($chunk->disabled) {
                return $this->error('Chunk is disabled', [], 423);
            }

            $params = $request->all();
            $output = $this->chunkService->execute($id, $params);

            return $this->success([
                'chunk_id' => $chunk->id,
                'chunk_name' => $chunk->name,
                'output' => $output,
                'executed_at' => now()->toISOString(),
            ], 'Chunk executed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to execute chunk');
        }
    }
}