<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\EventService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Events',
    description: 'Управление системными событиями и их плагинами'
)]
class EventController extends ApiController
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    #[OA\Get(
        path: '/api/elements/events',
        summary: 'Получить список событий',
        description: 'Возвращает список системных событий с пагинацией и фильтрацией',
        tags: ['Events'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'service', 'groupname'], default: 'name')
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
                description: 'Поиск по названию или группе',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'service',
                description: 'Фильтр по номеру сервиса',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'groupname',
                description: 'Фильтр по названию группы',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_plugins',
                description: 'Включить информацию о плагинах (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_plugins_count',
                description: 'Включить количество плагинов (true/false/1/0)',
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
                'sort_by' => 'nullable|string|in:id,name,service,groupname',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'service' => 'nullable|integer|min:0',
                'groupname' => 'nullable|string|max:255',
                'include_plugins' => 'nullable|boolean',
                'include_plugins_count' => 'nullable|boolean',
            ]);

            $paginator = $this->eventService->getAll($validated);
            
            $includePlugins = $request->get('include_plugins', false);
            $includePluginsCount = $request->get('include_plugins_count', false);
            
            $events = collect($paginator->items())->map(function($event) use ($includePlugins, $includePluginsCount) {
                return $this->eventService->formatEvent($event, $includePlugins, $includePluginsCount);
            });
            
            return $this->paginated($events, $paginator, 'Events retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch events');
        }
    }

    #[OA\Get(
        path: '/api/elements/events/{id}',
        summary: 'Получить информацию о событии',
        description: 'Возвращает детальную информацию о конкретном событии',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function show($id)
    {
        try {
            $event = $this->eventService->findById($id);
                
            if (!$event) {
                return $this->notFound('Event not found');
            }
            
            $formattedEvent = $this->eventService->formatEvent($event, true, true);
            
            return $this->success($formattedEvent, 'Event retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event');
        }
    }

    #[OA\Post(
        path: '/api/elements/events',
        summary: 'Создать новое событие',
        description: 'Создает новое системное событие',
        tags: ['Events'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'OnMyCustomEvent'),
                    new OA\Property(property: 'service', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'groupname', type: 'string', maxLength: 255, nullable: true, example: 'CustomEvents')
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
                'name' => 'required|string|max:255|unique:system_eventnames,name',
                'service' => 'nullable|integer|min:0',
                'groupname' => 'nullable|string|max:255',
            ]);

            $event = $this->eventService->create($validated);
            $formattedEvent = $this->eventService->formatEvent($event, false, false);
            
            return $this->created($formattedEvent, 'Event created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create event');
        }
    }

    #[OA\Put(
        path: '/api/elements/events/{id}',
        summary: 'Обновить информацию о событии',
        description: 'Обновляет информацию о существующем событии',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID события',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true, example: 'UpdatedEventName'),
                    new OA\Property(property: 'service', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'groupname', type: 'string', maxLength: 255, nullable: true, example: 'UpdatedGroup')
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
            $event = $this->eventService->findById($id);
                
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:system_eventnames,name,' . $id,
                'service' => 'nullable|integer|min:0',
                'groupname' => 'nullable|string|max:255',
            ]);

            $updatedEvent = $this->eventService->update($id, $validated);
            $formattedEvent = $this->eventService->formatEvent($updatedEvent, false, false);
            
            return $this->updated($formattedEvent, 'Event updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update event');
        }
    }

    #[OA\Delete(
        path: '/api/elements/events/{id}',
        summary: 'Удалить событие',
        description: 'Удаляет указанное событие (только если к нему не привязаны плагины)',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID события',
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
            $event = $this->eventService->findById($id);
                
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $this->eventService->delete($id);

            return $this->deleted('Event deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete event');
        }
    }

    #[OA\Get(
        path: '/api/elements/events/{id}/plugins',
        summary: 'Получить плагины события',
        description: 'Возвращает список плагинов, привязанных к указанному событию',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function plugins($id)
    {
        try {
            $event = $this->eventService->findById($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $plugins = $this->eventService->getEventPlugins($id);

            return $this->success([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'plugins' => $plugins,
                'plugins_count' => count($plugins),
                'enabled_plugins_count' => count(array_filter($plugins, function($plugin) {
                    return !$plugin['plugin_disabled'];
                })),
                'disabled_plugins_count' => count(array_filter($plugins, function($plugin) {
                    return $plugin['plugin_disabled'];
                })),
            ], 'Event plugins retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event plugins');
        }
    }

    #[OA\Post(
        path: '/api/elements/events/{id}/plugins',
        summary: 'Добавить плагин к событию',
        description: 'Привязывает плагин к событию с указанным приоритетом',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID события',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['plugin_id'],
                properties: [
                    new OA\Property(property: 'plugin_id', type: 'integer', example: 1),
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
    public function addPlugin(Request $request, $id)
    {
        try {
            $event = $this->eventService->findById($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $validated = $this->validateRequest($request, [
                'plugin_id' => 'required|integer|exists:site_plugins,id',
                'priority' => 'nullable|integer|min:0',
            ]);

            $result = $this->eventService->addPluginToEvent(
                $id, 
                $validated['plugin_id'], 
                $validated['priority'] ?? 0
            );

            return $this->success([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'plugin' => [
                    'id' => $result['plugin']->id,
                    'name' => $result['plugin']->name,
                    'priority' => $result['plugin_event']->priority,
                    'plugin_event_id' => $result['plugin_event']->id,
                ],
            ], 'Plugin added to event successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add plugin to event');
        }
    }

    #[OA\Delete(
        path: '/api/elements/events/{id}/plugins/{pluginId}',
        summary: 'Удалить плагин из события',
        description: 'Отвязывает плагин от события',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID события',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pluginId',
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
    public function removePlugin($id, $pluginId)
    {
        try {
            $event = $this->eventService->findById($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $this->eventService->removePluginFromEvent($id, $pluginId);

            return $this->deleted('Plugin removed from event successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove plugin from event');
        }
    }

    #[OA\Put(
        path: '/api/elements/events/{id}/plugins/{pluginId}/priority',
        summary: 'Обновить приоритет плагина',
        description: 'Изменяет приоритет выполнения плагина в событии',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID события',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pluginId',
                description: 'ID плагина',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['priority'],
                properties: [
                    new OA\Property(property: 'priority', type: 'integer', minimum: 0, example: 10)
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
    public function updatePluginPriority(Request $request, $id, $pluginId)
    {
        try {
            $event = $this->eventService->findById($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $validated = $this->validateRequest($request, [
                'priority' => 'required|integer|min:0',
            ]);

            $result = $this->eventService->updatePluginPriority($id, $pluginId, $validated['priority']);

            return $this->success([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'plugin' => [
                    'id' => $pluginId,
                    'name' => $result['plugin']->name,
                    'priority' => $result['plugin_event']->priority,
                ],
            ], 'Plugin priority updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin priority');
        }
    }

    #[OA\Get(
        path: '/api/elements/events/groups',
        summary: 'Получить список групп событий',
        description: 'Возвращает уникальные названия групп событий',
        tags: ['Events'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function groups()
    {
        try {
            $groups = $this->eventService->getEventGroups();

            return $this->success([
                'groups' => $groups,
                'groups_count' => count($groups),
            ], 'Event groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event groups');
        }
    }

    #[OA\Get(
        path: '/api/elements/events/by-group/{groupName}',
        summary: 'Получить события по группе',
        description: 'Возвращает все события, принадлежащие указанной группе',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'groupName',
                description: 'Название группы событий',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function byGroup($groupName)
    {
        try {
            $events = $this->eventService->getEventsByGroup($groupName);

            if (empty($events)) {
                return $this->notFound('No events found for the specified group');
            }

            return $this->success([
                'group' => $groupName,
                'events' => $events,
                'events_count' => count($events),
            ], 'Events by group retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch events by group');
        }
    }

    #[OA\Get(
        path: '/api/elements/events/services',
        summary: 'Получить список сервисов событий',
        description: 'Возвращает уникальные номера сервисов событий',
        tags: ['Events'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function services()
    {
        try {
            $services = $this->eventService->getEventServices();

            return $this->success([
                'services' => $services,
                'services_count' => count($services),
            ], 'Event services retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event services');
        }
    }

    #[OA\Get(
        path: '/api/elements/events/by-service/{service}',
        summary: 'Получить события по сервису',
        description: 'Возвращает все события, принадлежащие указанному сервису',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'service',
                description: 'Номер сервиса событий',
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
    public function byService($service)
    {
        try {
            $events = $this->eventService->getEventsByService((int)$service);

            if (empty($events)) {
                return $this->notFound('No events found for the specified service');
            }

            return $this->success([
                'service' => (int)$service,
                'events' => $events,
                'events_count' => count($events),
            ], 'Events by service retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch events by service');
        }
    }

    #[OA\Get(
        path: '/api/elements/events/search',
        summary: 'Поиск событий',
        description: 'Поиск событий по названию или группе',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'query',
                description: 'Строка поиска (мин. 2 символа)',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 255)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Максимальное количество результатов (1-50)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50, default: 10)
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function search(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'query' => 'required|string|min:2|max:255',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $events = $this->eventService->searchEvents(
                $validated['query'], 
                $validated['limit'] ?? 10
            );

            return $this->success([
                'query' => $validated['query'],
                'events' => $events,
                'events_count' => count($events),
            ], 'Events search completed successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to search events');
        }
    }
}