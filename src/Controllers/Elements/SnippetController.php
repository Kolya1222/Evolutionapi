<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\SnippetService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Snippets',
    description: 'Управление сниппетами Evolution CMS'
)]
class SnippetController extends ApiController
{
    protected $snippetService;

    public function __construct(SnippetService $snippetService)
    {
        $this->snippetService = $snippetService;
    }

    #[OA\Get(
        path: '/api/elements/snippets',
        summary: 'Получить список сниппетов',
        description: 'Возвращает список сниппетов с пагинацией и фильтрацией',
        tags: ['Snippets'],
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
                name: 'has_module',
                description: 'Фильтр по наличию модуля (true/false/1/0)',
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
            ),
            new OA\Parameter(
                name: 'include_module',
                description: 'Включить информацию о модуле (true/false/1/0)',
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
                'has_module' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_module' => 'nullable|boolean',
            ]);

            $paginator = $this->snippetService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeModule = $request->get('include_module', false);
            
            $snippets = collect($paginator->items())->map(function($snippet) use ($includeCategory, $includeModule) {
                return $this->snippetService->formatSnippet($snippet, $includeCategory, $includeModule);
            });
            
            return $this->paginated($snippets, $paginator, 'Snippets retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippets');
        }
    }

    #[OA\Get(
        path: '/api/elements/snippets/{id}',
        summary: 'Получить информацию о сниппете',
        description: 'Возвращает детальную информацию о конкретном сниппете',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }
            
            $formattedSnippet = $this->snippetService->formatSnippet($snippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets',
        summary: 'Создать новый сниппет',
        description: 'Создает новый сниппет с указанными параметрами',
        tags: ['Snippets'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'snippet'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'MySnippet'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Описание сниппета'),
                    new OA\Property(property: 'snippet', type: 'string', example: '<?php echo "Hello Snippet"; ?>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'cache_type', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'properties', type: 'string', nullable: true, example: 'key1=value1\nkey2=value2'),
                    new OA\Property(property: 'module_guid', type: 'string', maxLength: 255, nullable: true, example: '')
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
                'name' => 'required|string|max:255|unique:site_snippets,name',
                'description' => 'nullable|string',
                'snippet' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'module_guid' => 'nullable|string|max:255',
            ]);

            $snippet = $this->snippetService->create($validated);
            $formattedSnippet = $this->snippetService->formatSnippet($snippet, true, true);
            
            return $this->created($formattedSnippet, 'Snippet created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create snippet');
        }
    }

    #[OA\Put(
        path: '/api/elements/snippets/{id}',
        summary: 'Обновить информацию о сниппете',
        description: 'Обновляет информацию о существующем сниппете',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true, example: 'UpdatedSnippet'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Обновленное описание'),
                    new OA\Property(property: 'snippet', type: 'string', nullable: true, example: '<?php echo "Updated Snippet"; ?>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'cache_type', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'properties', type: 'string', nullable: true, example: 'newkey=newvalue'),
                    new OA\Property(property: 'module_guid', type: 'string', maxLength: 255, nullable: true, example: '')
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
            $snippet = $this->snippetService->findById($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_snippets,name,' . $id,
                'description' => 'nullable|string',
                'snippet' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'module_guid' => 'nullable|string|max:255',
            ]);

            $updatedSnippet = $this->snippetService->update($id, $validated);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->updated($formattedSnippet, 'Snippet updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update snippet');
        }
    }

    #[OA\Delete(
        path: '/api/elements/snippets/{id}',
        summary: 'Удалить сниппет',
        description: 'Удаляет указанный сниппет',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $this->snippetService->delete($id);

            return $this->deleted('Snippet deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/duplicate',
        summary: 'Дублировать сниппет',
        description: 'Создает копию существующего сниппета',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета для копирования',
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $newSnippet = $this->snippetService->duplicate($id);
            $formattedSnippet = $this->snippetService->formatSnippet($newSnippet, true, true);
            
            return $this->created($formattedSnippet, 'Snippet duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/enable',
        summary: 'Включить сниппет',
        description: 'Включает отключенный сниппет',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'disabled', false);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/disable',
        summary: 'Отключить сниппет',
        description: 'Отключает сниппет',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'disabled', true);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/lock',
        summary: 'Заблокировать сниппет',
        description: 'Блокирует сниппет от редактирования',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'locked', true);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/unlock',
        summary: 'Разблокировать сниппет',
        description: 'Разблокирует сниппет для редактирования',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'locked', false);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock snippet');
        }
    }

    #[OA\Get(
        path: '/api/elements/snippets/{id}/content',
        summary: 'Получить содержимое сниппета',
        description: 'Возвращает только содержимое (код) сниппета',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'content' => $snippet->snippet,
                'source_code' => $snippet->sourceCode,
            ], 'Snippet content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippet content');
        }
    }

    #[OA\Put(
        path: '/api/elements/snippets/{id}/content',
        summary: 'Обновить содержимое сниппета',
        description: 'Обновляет только содержимое (код) сниппета',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
                    new OA\Property(property: 'content', type: 'string', example: '<?php echo "New content"; ?>')
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $updatedSnippet = $this->snippetService->updateContent($id, $validated['content']);

            return $this->success([
                'snippet_id' => $updatedSnippet->id,
                'snippet_name' => $updatedSnippet->name,
                'content' => $updatedSnippet->snippet,
                'source_code' => $updatedSnippet->sourceCode,
            ], 'Snippet content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update snippet content');
        }
    }

    #[OA\Get(
        path: '/api/elements/snippets/{id}/properties',
        summary: 'Получить свойства сниппета',
        description: 'Возвращает свойства сниппета в разобранном виде',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
    public function properties($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $properties = $this->snippetService->parseProperties($snippet->properties);

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'properties' => $properties,
                'properties_raw' => $snippet->properties,
            ], 'Snippet properties retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippet properties');
        }
    }

    #[OA\Put(
        path: '/api/elements/snippets/{id}/properties',
        summary: 'Обновить свойства сниппета',
        description: 'Обновляет свойства сниппета в виде строки key=value',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['properties'],
                properties: [
                    new OA\Property(property: 'properties', type: 'string', example: 'key1=value1\nkey2=value2')
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
    public function updateProperties(Request $request, $id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $updatedSnippet = $this->snippetService->updateProperties($id, $validated['properties']);
            $properties = $this->snippetService->parseProperties($validated['properties']);

            return $this->success([
                'snippet_id' => $updatedSnippet->id,
                'snippet_name' => $updatedSnippet->name,
                'properties' => $properties,
                'properties_raw' => $updatedSnippet->properties,
            ], 'Snippet properties updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update snippet properties');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/execute',
        summary: 'Выполнить сниппет',
        description: 'Выполняет код сниппета с указанными параметрами и возвращает результат',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            if ($snippet->disabled) {
                return $this->error('Snippet is disabled', [], 423);
            }

            $params = $request->all();
            $output = $this->snippetService->execute($id, $params);

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'output' => $output,
                'executed_at' => now()->toISOString(),
            ], 'Snippet executed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to execute snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/attach-module',
        summary: 'Привязать модуль к сниппету',
        description: 'Привязывает модуль к сниппету по GUID модуля',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['module_guid'],
                properties: [
                    new OA\Property(property: 'module_guid', type: 'string', maxLength: 255, example: '12345678-1234-1234-1234-123456789012')
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
    public function attachModule(Request $request, $id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'module_guid' => 'required|string|max:255|exists:site_modules,guid',
            ]);

            $updatedSnippet = $this->snippetService->attachModule($id, $validated['module_guid']);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Module attached to snippet successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to attach module to snippet');
        }
    }

    #[OA\Post(
        path: '/api/elements/snippets/{id}/detach-module',
        summary: 'Отвязать модуль от сниппета',
        description: 'Отвязывает модуль от сниппета',
        tags: ['Snippets'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сниппета',
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
    public function detachModule($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->detachModule($id);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Module detached from snippet successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to detach module from snippet');
        }
    }
}