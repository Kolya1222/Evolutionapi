<?php

namespace roilafx\Evolutionapi\Controllers\Content;

use roilafx\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use roilafx\Evolutionapi\Services\Content\CategoryService;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Categories', 
    description: 'Управление категориями элементов (шаблоны, чанки, сниппеты, плагины, модули, TV)'
)]
class CategoryController extends ApiController
{
    private $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    #[OA\Get(
        path: '/api/contents/categories',
        summary: 'Получить список категорий',
        description: 'Возвращает список всех категорий с пагинацией, сортировкой и фильтрацией',
        tags: ['Categories'],
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
                description: 'Поле для сортировки: id, category, rank',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['id', 'category', 'rank'], default: 'rank')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Порядок сортировки: asc, desc',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Поиск по названию категории',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_counts',
                description: 'Включить количество элементов в категории',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string', 
                    enum: ['true', 'false', '1', '0'],
                    default: 'false'
                )
            ),
            new OA\Parameter(
                name: 'include_elements',
                description: 'Включить список элементов в категории',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string', 
                    enum: ['true', 'false', '1', '0'],
                    default: 'false'
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
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,category,rank',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_counts' => 'nullable|string|in:true,false,1,0',
                'include_elements' => 'nullable|string|in:true,false,1,0',
            ]);

            $query = Category::query();

            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where('category', 'LIKE', "%{$searchTerm}%");
            }

            $sortBy = $validated['sort_by'] ?? 'rank';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCounts = in_array($validated['include_counts'] ?? 'false', ['true', '1']);
            $includeElements = in_array($validated['include_elements'] ?? 'false', ['true', '1']);
            
            $formattedItems = collect($paginator->items())->map(function($category) use ($includeCounts, $includeElements) {
                return $this->formatCategory($category, $includeCounts, $includeElements);
            });
            
            return $this->paginated($formattedItems, $paginator, 'Categories retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch categories');
        }
    }

    #[OA\Get(
        path: '/api/contents/categories/{id}',
        summary: 'Получить категорию по ID',
        description: 'Возвращает информацию о категории',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID категории',
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
            $category = Category::find($id);
                
            if (!$category) {
                return $this->notFound('Category not found');
            }
            
            $formattedCategory = $this->formatCategory($category, true, true);
            
            return $this->success($formattedCategory, 'Category retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch category');
        }
    }

    #[OA\Post(
        path: '/api/contents/categories',
        summary: 'Создать новую категорию',
        description: 'Создает новую категорию',
        tags: ['Categories'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category'],
                properties: [
                    new OA\Property(property: 'category', type: 'string', maxLength: 45, example: 'Шаблоны'),
                    new OA\Property(property: 'rank', type: 'integer', minimum: 0, example: 0),
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
                'category' => 'required|string|max:45|unique:categories,category',
                'rank' => 'nullable|integer|min:0',
            ]);

            $category = $this->categoryService->createCategory($validated);

            $formatted = $this->formatCategory($category);
            
            return $this->created($formatted, 'Category created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create category');
        }
    }

    #[OA\Put(
        path: '/api/contents/categories/{id}',
        summary: 'Обновить категорию',
        description: 'Обновляет информацию о категории',
        tags: ['Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID категории',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'category', type: 'string', maxLength: 45, example: 'Обновленное название'),
                    new OA\Property(property: 'rank', type: 'integer', minimum: 0, example: 1),
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
            $category = Category::find($id);
                
            if (!$category) {
                return $this->notFound('Category not found');
            }

            $validated = $this->validateRequest($request, [
                'category' => 'sometimes|string|max:45|unique:categories,category,' . $id,
                'rank' => 'sometimes|integer|min:0',
            ]);

            $category = $this->categoryService->updateCategory($id, $validated);

            return $this->updated($this->formatCategory($category), 'Category updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update category');
        }
    }

    #[OA\Delete(
        path: '/api/contents/categories/{id}',
        summary: 'Удалить категорию',
        description: 'Удаляет категорию. Категорию можно удалить только если в ней нет элементов',
        tags: ['Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID категории',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
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
            $this->categoryService->deleteCategory($id);

            return $this->deleted('Category deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete category');
        }
    }

    #[OA\Get(
        path: '/api/contents/categories/{id}/elements',
        summary: 'Получить все элементы категории',
        description: 'Возвращает все элементы всех типов, принадлежащие категории',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID категории',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function allElements($id)
    {
        return $this->elements($id, null);
    }

    #[OA\Get(
        path: '/api/contents/categories/{id}/elements/{type}',
        summary: 'Получить элементы определенного типа в категории',
        description: 'Возвращает элементы определенного типа, принадлежащие категории',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID категории',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Тип элементов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['templates', 'chunks', 'snippets', 'plugins', 'modules', 'tvs'])
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function elementsByType($id, $type)
    {
        return $this->elements($id, $type);
    }

    #[OA\Get(
        path: '/api/contents/elements/uncategorized/{type}',
        summary: 'Получить элементы без категории',
        description: 'Возвращает элементы указанного типа, которые не принадлежат ни одной категории',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                description: 'Тип элементов',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['templates', 'chunks', 'snippets', 'plugins', 'modules', 'tvs'])
            ),
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function uncategorizedElements($type)
    {
        try {
            $validTypes = ['templates', 'chunks', 'snippets', 'plugins', 'modules', 'tvs'];
            
            if (!in_array($type, $validTypes)) {
                return $this->error('Invalid element type', [
                    'available_types' => $validTypes
                ], 422);
            }

            $elements = $this->categoryService->getElementsNotInCategory($type);
            
            return $this->success($elements, 'Uncategorized ' . $type . ' retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch uncategorized elements');
        }
    }

    #[OA\Post(
        path: '/api/contents/categories/{id}/move-elements',
        summary: 'Переместить элементы в другую категорию',
        description: 'Перемещает элементы из текущей категории в другую',
        tags: ['Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID исходной категории',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['target_category_id', 'element_types'],
                properties: [
                    new OA\Property(property: 'target_category_id', type: 'integer', example: 2),
                    new OA\Property(
                        property: 'element_types',
                        type: 'array',
                        items: new OA\Items(type: 'string', enum: ['templates', 'chunks', 'snippets', 'plugins', 'modules', 'tvs']),
                        example: ['templates', 'chunks']
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
    public function moveElements(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'target_category_id' => 'required|integer|exists:categories,id',
                'element_types' => 'required|array',
                'element_types.*' => 'string|in:templates,chunks,snippets,plugins,modules,tvs',
            ]);

            $result = $this->categoryService->moveElements(
                $id, 
                $validated['target_category_id'], 
                $validated['element_types']
            );

            return $this->success($result, 'Elements moved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move elements');
        }
    }

    #[OA\Get(
        path: '/api/contents/categories/stats',
        summary: 'Статистика по категориям',
        description: 'Возвращает статистику по всем категориям',
        tags: ['Categories'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500'),
        ]
    )]
    public function stats()
    {
        try {
            $stats = $this->categoryService->getCategoriesStats();
            
            return $this->success($stats, 'Categories statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch categories statistics');
        }
    }

    protected function elements($id, $type = null)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return $this->notFound('Category not found');
            }

            $validTypes = ['templates', 'chunks', 'snippets', 'plugins', 'modules', 'tvs'];
            
            if ($type && !in_array($type, $validTypes)) {
                return $this->error('Invalid element type', [
                    'available_types' => $validTypes
                ], 422);
            }

            $elements = [];
            
            if (!$type || $type === 'templates') {
                $elements['templates'] = $category->templates->map(function($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->templatename,
                        'description' => $template->description,
                        'locked' => (bool)$template->locked,
                        'created_at' => $this->categoryService->safeFormatDate($template->createdon),
                    ];
                });
            }

            if (!$type || $type === 'chunks') {
                $elements['chunks'] = $category->chunks->map(function($chunk) {
                    return [
                        'id' => $chunk->id,
                        'name' => $chunk->name,
                        'description' => $chunk->description,
                        'locked' => (bool)$chunk->locked,
                        'created_at' => $this->categoryService->safeFormatDate($chunk->createdon),
                    ];
                });
            }

            if (!$type || $type === 'snippets') {
                $elements['snippets'] = $category->snippets->map(function($snippet) {
                    return [
                        'id' => $snippet->id,
                        'name' => $snippet->name,
                        'description' => $snippet->description,
                        'locked' => (bool)$snippet->locked,
                        'created_at' => $this->categoryService->safeFormatDate($snippet->createdon),
                    ];
                });
            }

            if (!$type || $type === 'plugins') {
                $elements['plugins'] = $category->plugins->map(function($plugin) {
                    return [
                        'id' => $plugin->id,
                        'name' => $plugin->name,
                        'description' => $plugin->description,
                        'locked' => (bool)$plugin->locked,
                        'created_at' => $this->categoryService->safeFormatDate($plugin->createdon),
                    ];
                });
            }

            if (!$type || $type === 'modules') {
                $elements['modules'] = $category->modules->map(function($module) {
                    return [
                        'id' => $module->id,
                        'name' => $module->name,
                        'description' => $module->description,
                        'locked' => (bool)$module->locked,
                        'created_at' => $this->categoryService->safeFormatDate($module->createdon),
                    ];
                });
            }

            if (!$type || $type === 'tvs') {
                $elements['tvs'] = $category->tvs->map(function($tv) {
                    return [
                        'id' => $tv->id,
                        'name' => $tv->name,
                        'caption' => $tv->caption,
                        'type' => $tv->type,
                        'locked' => (bool)$tv->locked,
                    ];
                });
            }

            $message = $type 
                ? ucfirst($type) . ' in category retrieved successfully'
                : 'All elements in category retrieved successfully';

            return $this->success($elements, $message);

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch category elements');
        }
    }

    protected function formatCategory($category, $includeCounts = false, $includeElements = false)
    {
        $data = [
            'id' => $category->id,
            'name' => $category->category,
            'category' => $category->category,
            'rank' => $category->rank,
        ];

        if ($includeCounts) {
            $data['counts'] = [
                'templates' => $category->templates->count(),
                'chunks' => $category->chunks->count(),
                'snippets' => $category->snippets->count(),
                'plugins' => $category->plugins->count(),
                'modules' => $category->modules->count(),
                'tvs' => $category->tvs->count(),
                'total' => $category->templates->count() + $category->chunks->count() + 
                          $category->snippets->count() + $category->plugins->count() + 
                          $category->modules->count() + $category->tvs->count(),
            ];
        }

        if ($includeElements) {
            $data['elements'] = [
                'templates' => $category->templates->pluck('templatename', 'id'),
                'chunks' => $category->chunks->pluck('name', 'id'),
                'snippets' => $category->snippets->pluck('name', 'id'),
                'plugins' => $category->plugins->pluck('name', 'id'),
                'modules' => $category->modules->pluck('name', 'id'),
                'tvs' => $category->tvs->pluck('name', 'id'),
            ];
        }

        return $data;
    }
}