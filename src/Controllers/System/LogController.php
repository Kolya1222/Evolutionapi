<?php

namespace roilafx\Evolutionapi\Controllers\System;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\System\LogService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Logs',
    description: 'Управление логами системы Evolution CMS'
)]
class LogController extends ApiController
{
    protected $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    #[OA\Get(
        path: '/api/systems/logs/event-logs',
        summary: 'Получить логи событий',
        description: 'Возвращает список логов событий с пагинацией и фильтрацией',
        tags: ['Logs'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'createdon', 'eventid', 'type', 'user', 'source'], default: 'createdon')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Порядок сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Поиск по описанию или источнику',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Фильтр по типу события (1-информация, 2-предупреждение, 3-ошибка)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', enum: [1, 2, 3])
            ),
            new OA\Parameter(
                name: 'eventid',
                description: 'Фильтр по ID события',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'usertype',
                description: 'Фильтр по типу пользователя (0-менеджер, 1-веб)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', enum: [0, 1])
            ),
            new OA\Parameter(
                name: 'user_id',
                description: 'Фильтр по ID пользователя',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'source',
                description: 'Фильтр по источнику',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'date_from',
                description: 'Фильтр по дате начала (YYYY-MM-DD)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'date_to',
                description: 'Фильтр по дате окончания (YYYY-MM-DD)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'include_user',
                description: 'Включить информацию о пользователе (true/false/1/0)',
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
    public function eventLogs(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,createdon,eventid,type,user,source',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'type' => 'nullable|integer|in:1,2,3',
                'eventid' => 'nullable|integer|min:0',
                'usertype' => 'nullable|integer|in:0,1',
                'user_id' => 'nullable|integer|exists:users,id',
                'source' => 'nullable|string|max:255',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'include_user' => 'nullable|boolean',
            ]);

            $paginator = $this->logService->getEventLogs($validated);
            
            $includeUser = $request->get('include_user', false);
            
            $logs = collect($paginator->items())->map(function($log) use ($includeUser) {
                return $this->logService->formatEventLog($log, $includeUser);
            });
            
            return $this->paginated($logs, $paginator, 'Event logs retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event logs');
        }
    }

    #[OA\Get(
        path: '/api/systems/logs/event-logs/{id}',
        summary: 'Получить информацию о логе события',
        description: 'Возвращает детальную информацию о конкретном логе события',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID лога события',
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
    public function showEventLog($id)
    {
        try {
            $log = $this->logService->findEventLog($id);
                
            if (!$log) {
                return $this->notFound('Event log not found');
            }
            
            $formattedLog = $this->logService->formatEventLog($log, true);
            
            return $this->success($formattedLog, 'Event log retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event log');
        }
    }

    #[OA\Get(
        path: '/api/systems/logs/manager-logs',
        summary: 'Получить логи менеджера',
        description: 'Возвращает список логов действий менеджеров с пагинацией и фильтрацией',
        tags: ['Logs'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'timestamp', 'internalKey', 'action'], default: 'timestamp')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Порядок сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Поиск по сообщению, имени элемента или имени пользователя',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'action',
                description: 'Фильтр по действию (ID действия)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'internalKey',
                description: 'Фильтр по ID пользователя менеджера',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'username',
                description: 'Фильтр по имени пользователя',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'itemid',
                description: 'Фильтр по ID элемента',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'date_from',
                description: 'Фильтр по дате начала (YYYY-MM-DD)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'date_to',
                description: 'Фильтр по дате окончания (YYYY-MM-DD)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'include_user',
                description: 'Включить информацию о пользователе (true/false/1/0)',
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
    public function managerLogs(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,timestamp,internalKey,action',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'action' => 'nullable|integer|min:0',
                'internalKey' => 'nullable|integer|exists:users,id',
                'username' => 'nullable|string|max:255',
                'itemid' => 'nullable|string|max:255',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'include_user' => 'nullable|boolean',
            ]);

            $paginator = $this->logService->getManagerLogs($validated);
            
            $includeUser = $request->get('include_user', false);
            
            $logs = collect($paginator->items())->map(function($log) use ($includeUser) {
                return $this->logService->formatManagerLog($log, $includeUser);
            });
            
            return $this->paginated($logs, $paginator, 'Manager logs retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch manager logs');
        }
    }

    #[OA\Get(
        path: '/api/systems/logs/manager-logs/{id}',
        summary: 'Получить информацию о логе менеджера',
        description: 'Возвращает детальную информацию о конкретном логе менеджера',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID лога менеджера',
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
    public function showManagerLog($id)
    {
        try {
            $log = $this->logService->findManagerLog($id);
                
            if (!$log) {
                return $this->notFound('Manager log not found');
            }
            
            $formattedLog = $this->logService->formatManagerLog($log, true);
            
            return $this->success($formattedLog, 'Manager log retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch manager log');
        }
    }

    #[OA\Post(
        path: '/api/systems/logs/event-logs',
        summary: 'Создать лог события',
        description: 'Создает новую запись в логе событий',
        tags: ['Logs'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['eventid', 'type', 'usertype', 'source', 'description'],
                properties: [
                    new OA\Property(property: 'eventid', type: 'integer', minimum: 0, example: 100),
                    new OA\Property(property: 'type', type: 'integer', enum: [1, 2, 3], example: 1),
                    new OA\Property(property: 'usertype', type: 'integer', enum: [0, 1], example: 0),
                    new OA\Property(property: 'user', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'source', type: 'string', maxLength: 255, example: 'API'),
                    new OA\Property(property: 'description', type: 'string', example: 'Событие выполнено успешно')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function createEventLog(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'eventid' => 'required|integer|min:0',
                'type' => 'required|integer|in:1,2,3',
                'usertype' => 'required|integer|in:0,1',
                'user' => 'nullable|integer|exists:users,id',
                'source' => 'required|string|max:255',
                'description' => 'required|string',
            ]);

            $log = $this->logService->createEventLog($validated);
            $formattedLog = $this->logService->formatEventLog($log, false);
            
            return $this->created($formattedLog, 'Event log created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create event log');
        }
    }

    #[OA\Post(
        path: '/api/systems/logs/manager-logs',
        summary: 'Создать лог менеджера',
        description: 'Создает новую запись в логе действий менеджеров',
        tags: ['Logs'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['internalKey', 'username', 'action', 'message'],
                properties: [
                    new OA\Property(property: 'internalKey', type: 'integer', example: 1),
                    new OA\Property(property: 'username', type: 'string', maxLength: 255, example: 'admin'),
                    new OA\Property(property: 'action', type: 'integer', minimum: 0, example: 1),
                    new OA\Property(property: 'itemid', type: 'string', maxLength: 255, nullable: true, example: '123'),
                    new OA\Property(property: 'itemname', type: 'string', maxLength: 255, nullable: true, example: 'Документ 123'),
                    new OA\Property(property: 'message', type: 'string', example: 'Пользователь вошел в систему'),
                    new OA\Property(property: 'ip', type: 'string', nullable: true, example: '192.168.1.1'),
                    new OA\Property(property: 'useragent', type: 'string', maxLength: 500, nullable: true, example: 'Mozilla/5.0...')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function createManagerLog(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'internalKey' => 'required|integer|exists:users,id',
                'username' => 'required|string|max:255',
                'action' => 'required|integer|min:0',
                'itemid' => 'nullable|string|max:255',
                'itemname' => 'nullable|string|max:255',
                'message' => 'required|string',
                'ip' => 'nullable|ip',
                'useragent' => 'nullable|string|max:500',
            ]);

            $log = $this->logService->createManagerLog($validated);
            $formattedLog = $this->logService->formatManagerLog($log, true);
            
            return $this->created($formattedLog, 'Manager log created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create manager log');
        }
    }

    #[OA\Delete(
        path: '/api/systems/logs/event-logs/{id}',
        summary: 'Удалить лог события',
        description: 'Удаляет указанную запись из лога событий',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID лога события',
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
    public function deleteEventLog($id)
    {
        try {
            $log = $this->logService->findEventLog($id);
                
            if (!$log) {
                return $this->notFound('Event log not found');
            }

            $this->logService->deleteEventLog($id);

            return $this->deleted('Event log deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete event log');
        }
    }

    #[OA\Delete(
        path: '/api/systems/logs/manager-logs/{id}',
        summary: 'Удалить лог менеджера',
        description: 'Удаляет указанную запись из лога менеджеров',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID лога менеджера',
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
    public function deleteManagerLog($id)
    {
        try {
            $log = $this->logService->findManagerLog($id);
                
            if (!$log) {
                return $this->notFound('Manager log not found');
            }

            $this->logService->deleteManagerLog($id);

            return $this->deleted('Manager log deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete manager log');
        }
    }

    #[OA\Delete(
        path: '/api/systems/logs/event-logs/clear',
        summary: 'Очистить логи событий',
        description: 'Очищает логи событий с фильтрацией по времени и типу',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'older_than_days',
                description: 'Удалить логи старше указанного количества дней',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1)
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Фильтр по типу события для удаления (1-информация, 2-предупреждение, 3-ошибка)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', enum: [1, 2, 3])
            ),
            new OA\Parameter(
                name: 'confirm',
                description: 'Подтверждение операции (true/false/1/0)',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function clearEventLogs(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'older_than_days' => 'nullable|integer|min:1',
                'type' => 'nullable|integer|in:1,2,3',
                'confirm' => 'required|boolean|accepted',
            ]);

            if (!$validated['confirm']) {
                return $this->error('Confirmation required', [], 422);
            }

            $deletedCount = $this->logService->clearEventLogs($validated);

            return $this->success([
                'deleted_count' => $deletedCount,
                'cleared_at' => date('Y-m-d H:i:s'),
            ], 'Event logs cleared successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to clear event logs');
        }
    }

    #[OA\Delete(
        path: '/api/systems/logs/manager-logs/clear',
        summary: 'Очистить логи менеджеров',
        description: 'Очищает логи действий менеджеров с фильтрацией по времени и действию',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'older_than_days',
                description: 'Удалить логи старше указанного количества дней',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1)
            ),
            new OA\Parameter(
                name: 'action',
                description: 'Фильтр по действию для удаления',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'confirm',
                description: 'Подтверждение операции (true/false/1/0)',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function clearManagerLogs(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'older_than_days' => 'nullable|integer|min:1',
                'action' => 'nullable|integer|min:0',
                'confirm' => 'required|boolean|accepted',
            ]);

            if (!$validated['confirm']) {
                return $this->error('Confirmation required', [], 422);
            }

            $deletedCount = $this->logService->clearManagerLogs($validated);

            return $this->success([
                'deleted_count' => $deletedCount,
                'cleared_at' => date('Y-m-d H:i:s'),
            ], 'Manager logs cleared successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to clear manager logs');
        }
    }

    #[OA\Get(
        path: '/api/systems/logs/event-logs/stats',
        summary: 'Статистика логов событий',
        description: 'Возвращает статистику по логам событий за указанный период',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'days',
                description: 'Количество дней для статистики (1-365)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 365, default: 30)
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function eventLogStats(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'days' => 'nullable|integer|min:1|max:365',
            ]);

            $stats = $this->logService->getEventLogStats($validated['days'] ?? 30);

            return $this->success($stats, 'Event log statistics retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event log statistics');
        }
    }

    #[OA\Get(
        path: '/api/systems/logs/manager-logs/stats',
        summary: 'Статистика логов менеджеров',
        description: 'Возвращает статистику по логам действий менеджеров за указанный период',
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(
                name: 'days',
                description: 'Количество дней для статистики (1-365)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 365, default: 30)
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function managerLogStats(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'days' => 'nullable|integer|min:1|max:365',
            ]);

            $stats = $this->logService->getManagerLogStats($validated['days'] ?? 30);

            return $this->success($stats, 'Manager log statistics retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch manager log statistics');
        }
    }
}