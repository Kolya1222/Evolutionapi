<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\PluginService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Plugins',
    description: 'Управление плагинами Evolution CMS'
)]
class PluginController extends ApiController
{
    protected $pluginService;

    public function __construct(PluginService $pluginService)
    {
        $this->pluginService = $pluginService;
    }

    #[OA\Get(
        path: '/api/elements/plugins',
        summary: 'Получить список плагинов',
        description: 'Возвращает список плагинов с пагинацией и фильтрацией',
        tags: ['Plugins'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'createdon', 'editedon', 'priority'], default: 'name')
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
            ),
            new OA\Parameter(
                name: 'include_events',
                description: 'Включить информацию о событиях (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_alternative',
                description: 'Включить информацию об альтернативных плагинах (true/false/1/0)',
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
                'sort_by' => 'nullable|string|in:id,name,createdon,editedon,priority',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'cache_type' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_events' => 'nullable|boolean',
                'include_alternative' => 'nullable|boolean',
            ]);

            $paginator = $this->pluginService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeEvents = $request->get('include_events', false);
            $includeAlternative = $request->get('include_alternative', false);
            
            $plugins = collect($paginator->items())->map(function($plugin) use ($includeCategory, $includeEvents, $includeAlternative) {
                return $this->pluginService->formatPlugin($plugin, $includeCategory, $includeEvents, $includeAlternative);
            });
            
            return $this->paginated($plugins, $paginator, 'Plugins retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugins');
        }
    }

    #[OA\Get(
        path: '/api/elements/plugins/{id}',
        summary: 'Получить информацию о плагине',
        description: 'Возвращает детальную информацию о конкретном плагине',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            $plugin = $this->pluginService->findById($id);
                
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }
            
            $formattedPlugin = $this->pluginService->formatPlugin($plugin, true, true, true);
            
            return $this->success($formattedPlugin, 'Plugin retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin');
        }
    }

    #[OA\Post(
        path: '/api/elements/plugins',
        summary: 'Создать новый плагин',
        description: 'Создает новый плагин с указанными параметрами и событиями',
        tags: ['Plugins'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'plugincode'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'MyPlugin'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Описание плагина'),
                    new OA\Property(property: 'plugincode', type: 'string', example: '<?php echo "Hello Plugin"; ?>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'cache_type', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'properties', type: 'string', nullable: true, example: 'key1=value1\nkey2=value2'),
                    new OA\Property(property: 'moduleguid', type: 'string', maxLength: 255, nullable: true, example: ''),
                    new OA\Property(
                        property: 'events',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'evtid', type: 'integer', example: 1),
                                new OA\Property(property: 'priority', type: 'integer', minimum: 0, nullable: true, example: 0)
                            ]
                        )
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
                'name' => 'required|string|max:255|unique:site_plugins,name',
                'description' => 'nullable|string',
                'plugincode' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'moduleguid' => 'nullable|string|max:255',
                'events' => 'nullable|array',
                'events.*.evtid' => 'required|integer|exists:system_eventnames,id',
                'events.*.priority' => 'nullable|integer|min:0',
            ]);

            $plugin = $this->pluginService->create($validated);
            $formattedPlugin = $this->pluginService->formatPlugin($plugin, true, true, false);
            
            return $this->created($formattedPlugin, 'Plugin created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create plugin');
        }
    }

    #[OA\Put(
        path: '/api/elements/plugins/{id}',
        summary: 'Обновить информацию о плагине',
        description: 'Обновляет информацию о существующем плагине',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true, example: 'UpdatedPlugin'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Обновленное описание'),
                    new OA\Property(property: 'plugincode', type: 'string', nullable: true, example: '<?php echo "Updated Plugin"; ?>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'cache_type', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'properties', type: 'string', nullable: true, example: 'newkey=newvalue'),
                    new OA\Property(property: 'moduleguid', type: 'string', maxLength: 255, nullable: true, example: ''),
                    new OA\Property(
                        property: 'events',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'evtid', type: 'integer', example: 2),
                                new OA\Property(property: 'priority', type: 'integer', minimum: 0, nullable: true, example: 1)
                            ]
                        )
                    )
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
    public function update(Request $request, $id)
    {
        try {
            $plugin = $this->pluginService->findById($id);
                
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_plugins,name,' . $id,
                'description' => 'nullable|string',
                'plugincode' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'moduleguid' => 'nullable|string|max:255',
                'events' => 'nullable|array',
                'events.*.evtid' => 'required|integer|exists:system_eventnames,id',
                'events.*.priority' => 'nullable|integer|min:0',
            ]);

            $updatedPlugin = $this->pluginService->update($id, $validated);
            $formattedPlugin = $this->pluginService->formatPlugin($updatedPlugin, true, true, false);
            
            return $this->updated($formattedPlugin, 'Plugin updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin');
        }
    }

    #[OA\Delete(
        path: '/api/elements/plugins/{id}',
        summary: 'Удалить плагин',
        description: 'Удаляет указанный плагин',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function destroy($id)
    {
        try {
            $plugin = $this->pluginService->findById($id);
                
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $this->pluginService->delete($id);

            return $this->deleted('Plugin deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete plugin');
        }
    }

    #[OA\Post(
        path: '/api/elements/plugins/{id}/duplicate',
        summary: 'Дублировать плагин',
        description: 'Создает копию существующего плагина',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина для копирования',
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
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $newPlugin = $this->pluginService->duplicate($id);
            $formattedPlugin = $this->pluginService->formatPlugin($newPlugin, true, true, false);
            
            return $this->created($formattedPlugin, 'Plugin duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate plugin');
        }
    }

    #[OA\Post(
        path: '/api/elements/plugins/{id}/enable',
        summary: 'Включить плагин',
        description: 'Включает отключенный плагин',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $updatedPlugin = $this->pluginService->toggleStatus($id, 'disabled', false);
            $formattedPlugin = $this->pluginService->formatPlugin($updatedPlugin, true, true, true);
            
            return $this->success($formattedPlugin, 'Plugin enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable plugin');
        }
    }

    #[OA\Post(
        path: '/api/elements/plugins/{id}/disable',
        summary: 'Отключить плагин',
        description: 'Отключает плагин',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $updatedPlugin = $this->pluginService->toggleStatus($id, 'disabled', true);
            $formattedPlugin = $this->pluginService->formatPlugin($updatedPlugin, true, true, true);
            
            return $this->success($formattedPlugin, 'Plugin disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable plugin');
        }
    }

    #[OA\Post(
        path: '/api/elements/plugins/{id}/lock',
        summary: 'Заблокировать плагин',
        description: 'Блокирует плагин от редактирования',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $updatedPlugin = $this->pluginService->toggleStatus($id, 'locked', true);
            $formattedPlugin = $this->pluginService->formatPlugin($updatedPlugin, true, true, true);
            
            return $this->success($formattedPlugin, 'Plugin locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock plugin');
        }
    }

    #[OA\Post(
        path: '/api/elements/plugins/{id}/unlock',
        summary: 'Разблокировать плагин',
        description: 'Разблокирует плагин для редактирования',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $updatedPlugin = $this->pluginService->toggleStatus($id, 'locked', false);
            $formattedPlugin = $this->pluginService->formatPlugin($updatedPlugin, true, true, true);
            
            return $this->success($formattedPlugin, 'Plugin unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock plugin');
        }
    }

    #[OA\Get(
        path: '/api/elements/plugins/{id}/content',
        summary: 'Получить содержимое плагина',
        description: 'Возвращает только содержимое (код) плагина',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'content' => $plugin->plugincode,
            ], 'Plugin content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin content');
        }
    }

    #[OA\Put(
        path: '/api/elements/plugins/{id}/content',
        summary: 'Обновить содержимое плагина',
        description: 'Обновляет только содержимое (код) плагина',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function updateContent(Request $request, $id)
    {
        try {
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $updatedPlugin = $this->pluginService->updateContent($id, $validated['content']);

            return $this->success([
                'plugin_id' => $updatedPlugin->id,
                'plugin_name' => $updatedPlugin->name,
                'content' => $updatedPlugin->plugincode,
            ], 'Plugin content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin content');
        }
    }

    #[OA\Get(
        path: '/api/elements/plugins/{id}/events',
        summary: 'Получить события плагина',
        description: 'Возвращает список событий, к которым привязан плагин',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
    public function events($id)
    {
        try {
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $events = $this->pluginService->getPluginEvents($id);

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'events' => $events,
                'events_count' => count($events),
            ], 'Plugin events retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin events');
        }
    }

    #[OA\Post(
        path: '/api/elements/plugins/{id}/events',
        summary: 'Добавить событие к плагину',
        description: 'Привязывает плагин к событию с указанным приоритетом',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['event_id'],
                properties: [
                    new OA\Property(property: 'event_id', type: 'integer', example: 1),
                    new OA\Property(property: 'priority', type: 'integer', minimum: 0, nullable: true, example: 0)
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
    public function addEvent(Request $request, $id)
    {
        try {
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $validated = $this->validateRequest($request, [
                'event_id' => 'required|integer|exists:system_eventnames,id',
                'priority' => 'nullable|integer|min:0',
            ]);

            $result = $this->pluginService->addEvent($id, $validated['event_id'], $validated['priority'] ?? 0);

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'event' => $result['event'],
            ], 'Event added to plugin successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add event to plugin');
        }
    }

    #[OA\Delete(
        path: '/api/elements/plugins/{id}/events/{eventId}',
        summary: 'Удалить событие из плагина',
        description: 'Отвязывает плагин от события',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'eventId',
                description: 'ID события',
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
    public function removeEvent($id, $eventId)
    {
        try {
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $this->pluginService->removeEvent($id, $eventId);

            return $this->deleted('Event removed from plugin successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove event from plugin');
        }
    }

    #[OA\Get(
        path: '/api/elements/plugins/{id}/properties',
        summary: 'Получить свойства плагина',
        description: 'Возвращает свойства плагина в разобранном виде',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $properties = $this->pluginService->parseProperties($plugin->properties);

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'properties' => $properties,
                'properties_raw' => $plugin->properties,
            ], 'Plugin properties retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin properties');
        }
    }

    #[OA\Put(
        path: '/api/elements/plugins/{id}/properties',
        summary: 'Обновить свойства плагина',
        description: 'Обновляет свойства плагина в виде строки key=value',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function updateProperties(Request $request, $id)
    {
        try {
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $updatedPlugin = $this->pluginService->updateProperties($id, $validated['properties']);
            $properties = $this->pluginService->parseProperties($validated['properties']);

            return $this->success([
                'plugin_id' => $updatedPlugin->id,
                'plugin_name' => $updatedPlugin->name,
                'properties' => $properties,
                'properties_raw' => $updatedPlugin->properties,
            ], 'Plugin properties updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin properties');
        }
    }

    #[OA\Get(
        path: '/api/elements/plugins/{id}/alternative',
        summary: 'Получить альтернативные плагины',
        description: 'Возвращает список альтернативных плагинов',
        tags: ['Plugins'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID плагина',
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
    public function alternative($id)
    {
        try {
            $plugin = $this->pluginService->findById($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $alternative = $this->pluginService->getAlternativePlugins($id);

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'alternative' => $alternative,
                'alternative_count' => count($alternative),
            ], 'Plugin alternatives retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin alternatives');
        }
    }
}