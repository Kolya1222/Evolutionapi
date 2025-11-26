<?php

namespace EvolutionCMS\Evolutionapi\Controllers\System;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\EventLog;
use EvolutionCMS\Models\ManagerLog;
use EvolutionCMS\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LogController extends ApiController
{
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

            $query = EventLog::query();

            // Поиск по описанию или источнику
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('source', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Фильтр по типу события
            if ($request->has('type')) {
                $query->where('type', $validated['type']);
            }

            // Фильтр по ID события
            if ($request->has('eventid')) {
                $query->where('eventid', $validated['eventid']);
            }

            // Фильтр по типу пользователя
            if ($request->has('usertype')) {
                $query->where('usertype', $validated['usertype']);
            }

            // Фильтр по пользователю
            if ($request->has('user_id')) {
                $query->where('user', $validated['user_id']);
            }

            // Фильтр по источнику
            if ($request->has('source')) {
                $query->where('source', 'LIKE', "%{$validated['source']}%");
            }

            // Фильтр по дате
            if ($request->has('date_from')) {
                $query->where('createdon', '>=', strtotime($validated['date_from']));
            }

            if ($request->has('date_to')) {
                $query->where('createdon', '<=', strtotime($validated['date_to']));
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'createdon';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeUser = $request->get('include_user', false);
            
            // Форматируем данные
            $logs = collect($paginator->items())->map(function($log) use ($includeUser) {
                return $this->formatEventLog($log, $includeUser);
            });
            
            return $this->paginated($logs, $paginator, 'Event logs retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event logs');
        }
    }

    public function showEventLog($id)
    {
        try {
            $log = EventLog::find($id);
                
            if (!$log) {
                return $this->notFound('Event log not found');
            }
            
            $formattedLog = $this->formatEventLog($log, true);
            
            return $this->success($formattedLog, 'Event log retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event log');
        }
    }

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

            $query = ManagerLog::query();

            // Поиск по сообщению, имени элемента или имени пользователя
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('message', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('itemname', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('username', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Фильтр по действию
            if ($request->has('action')) {
                $query->where('action', $validated['action']);
            }

            // Фильтр по пользователю (internalKey)
            if ($request->has('internalKey')) {
                $query->where('internalKey', $validated['internalKey']);
            }

            // Фильтр по имени пользователя
            if ($request->has('username')) {
                $query->where('username', 'LIKE', "%{$validated['username']}%");
            }

            // Фильтр по ID элемента
            if ($request->has('itemid')) {
                $query->where('itemid', $validated['itemid']);
            }

            // Фильтр по дате
            if ($request->has('date_from')) {
                $query->where('timestamp', '>=', strtotime($validated['date_from']));
            }

            if ($request->has('date_to')) {
                $query->where('timestamp', '<=', strtotime($validated['date_to']));
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'timestamp';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeUser = $request->get('include_user', false);
            
            // Форматируем данные
            $logs = collect($paginator->items())->map(function($log) use ($includeUser) {
                return $this->formatManagerLog($log, $includeUser);
            });
            
            return $this->paginated($logs, $paginator, 'Manager logs retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch manager logs');
        }
    }

    public function showManagerLog($id)
    {
        try {
            $log = ManagerLog::find($id);
                
            if (!$log) {
                return $this->notFound('Manager log not found');
            }
            
            $formattedLog = $this->formatManagerLog($log, true);
            
            return $this->success($formattedLog, 'Manager log retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch manager log');
        }
    }

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

            $logData = [
                'eventid' => $validated['eventid'],
                'type' => $validated['type'],
                'usertype' => $validated['usertype'],
                'user' => $validated['user'] ?? 0,
                'source' => $validated['source'],
                'description' => $validated['description'],
                'createdon' => time(),
            ];

            $log = EventLog::create($logData);

            $formattedLog = $this->formatEventLog($log, false);
            
            return $this->created($formattedLog, 'Event log created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create event log');
        }
    }

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

            $logData = [
                'internalKey' => $validated['internalKey'],
                'username' => $validated['username'],
                'action' => $validated['action'],
                'itemid' => $validated['itemid'] ?? '',
                'itemname' => $validated['itemname'] ?? '',
                'message' => $validated['message'],
                'ip' => $validated['ip'] ?? request()->ip(),
                'useragent' => $validated['useragent'] ?? request()->userAgent(),
                'timestamp' => time(),
            ];

            $log = ManagerLog::create($logData);

            $formattedLog = $this->formatManagerLog($log, true);
            
            return $this->created($formattedLog, 'Manager log created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create manager log');
        }
    }

    public function deleteEventLog($id)
    {
        try {
            $log = EventLog::find($id);
                
            if (!$log) {
                return $this->notFound('Event log not found');
            }

            $log->delete();

            return $this->deleted('Event log deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete event log');
        }
    }

    public function deleteManagerLog($id)
    {
        try {
            $log = ManagerLog::find($id);
                
            if (!$log) {
                return $this->notFound('Manager log not found');
            }

            $log->delete();

            return $this->deleted('Manager log deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete manager log');
        }
    }

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

            $query = EventLog::query();

            if ($request->has('older_than_days')) {
                $cutoffTime = time() - ($validated['older_than_days'] * 24 * 60 * 60);
                $query->where('createdon', '<', $cutoffTime);
            }

            if ($request->has('type')) {
                $query->where('type', $validated['type']);
            }

            $deletedCount = $query->delete();

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

            $query = ManagerLog::query();

            if ($request->has('older_than_days')) {
                $cutoffTime = time() - ($validated['older_than_days'] * 24 * 60 * 60);
                $query->where('timestamp', '<', $cutoffTime);
            }

            if ($request->has('action')) {
                $query->where('action', $validated['action']);
            }

            $deletedCount = $query->delete();

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

    public function eventLogStats(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'days' => 'nullable|integer|min:1|max:365',
            ]);

            $days = $validated['days'] ?? 30;
            $cutoffTime = time() - ($days * 24 * 60 * 60);

            $stats = [
                'period_days' => $days,
                'total_logs' => EventLog::where('createdon', '>=', $cutoffTime)->count(),
                'information_logs' => EventLog::where('type', EventLog::TYPE_INFORMATION)
                    ->where('createdon', '>=', $cutoffTime)->count(),
                'warning_logs' => EventLog::where('type', EventLog::TYPE_WARNING)
                    ->where('createdon', '>=', $cutoffTime)->count(),
                'error_logs' => EventLog::where('type', EventLog::TYPE_ERROR)
                    ->where('createdon', '>=', $cutoffTime)->count(),
                'manager_users' => EventLog::where('usertype', EventLog::USER_MGR)
                    ->where('createdon', '>=', $cutoffTime)->distinct('user')->count(),
                'web_users' => EventLog::where('usertype', EventLog::USER_WEB)
                    ->where('createdon', '>=', $cutoffTime)->distinct('user')->count(),
                'top_sources' => EventLog::where('createdon', '>=', $cutoffTime)
                    ->groupBy('source')
                    ->selectRaw('source, COUNT(*) as count')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($item) {
                        return [
                            'source' => $item->source,
                            'count' => $item->count,
                        ];
                    }),
            ];

            return $this->success($stats, 'Event log statistics retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event log statistics');
        }
    }

    public function managerLogStats(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'days' => 'nullable|integer|min:1|max:365',
            ]);

            $days = $validated['days'] ?? 30;
            $cutoffTime = time() - ($days * 24 * 60 * 60);

            $stats = [
                'period_days' => $days,
                'total_logs' => ManagerLog::where('timestamp', '>=', $cutoffTime)->count(),
                'unique_users' => ManagerLog::where('timestamp', '>=', $cutoffTime)
                    ->distinct('internalKey')->count(),
                'top_actions' => ManagerLog::where('timestamp', '>=', $cutoffTime)
                    ->groupBy('action')
                    ->selectRaw('action, COUNT(*) as count')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($item) {
                        return [
                            'action' => $item->action,
                            'count' => $item->count,
                        ];
                    }),
                'top_users' => ManagerLog::where('timestamp', '>=', $cutoffTime)
                    ->groupBy('internalKey', 'username')
                    ->selectRaw('internalKey, username, COUNT(*) as count')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($item) {
                        return [
                            'user_id' => $item->internalKey,
                            'username' => $item->username,
                            'action_count' => $item->count,
                        ];
                    }),
            ];

            return $this->success($stats, 'Manager log statistics retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch manager log statistics');
        }
    }

    protected function formatEventLog($log, $includeUser = false)
    {
        $data = [
            'id' => $log->id,
            'eventid' => $log->eventid,
            'type' => $log->type,
            'type_name' => $this->getEventLogTypeName($log->type),
            'usertype' => $log->usertype,
            'usertype_name' => $this->getUserTypeName($log->usertype),
            'source' => $log->source,
            'description' => $log->description,
            'created_at' => $this->safeFormatDate($log->createdon),
        ];

        if ($includeUser && $log->user > 0) {
            $user = $log->getUser();
            if ($user) {
                $data['user'] = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->fullname ?? '',
                ];
            } else {
                $data['user'] = null;
            }
        }

        return $data;
    }

    protected function formatManagerLog($log, $includeUser = false)
    {
        $data = [
            'id' => $log->id,
            'internalKey' => $log->internalKey,
            'username' => $log->username,
            'action' => $log->action,
            'itemid' => $log->itemid,
            'itemname' => $log->itemname,
            'message' => $log->message,
            'ip' => $log->ip,
            'useragent' => $log->useragent,
            'created_at' => $this->safeFormatDate($log->timestamp),
        ];

        if ($includeUser) {
            $user = User::find($log->internalKey);
            if ($user) {
                $data['user_info'] = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->fullname ?? '',
                    'email' => $user->email ?? '',
                ];
            } else {
                $data['user_info'] = null;
            }
        }

        return $data;
    }

    protected function getEventLogTypeName($type)
    {
        $types = [
            EventLog::TYPE_INFORMATION => 'information',
            EventLog::TYPE_WARNING => 'warning',
            EventLog::TYPE_ERROR => 'error',
        ];

        return $types[$type] ?? 'unknown';
    }

    protected function getUserTypeName($userType)
    {
        $types = [
            EventLog::USER_MGR => 'manager',
            EventLog::USER_WEB => 'web',
        ];

        return $types[$userType] ?? 'unknown';
    }

    protected function safeFormatDate($dateValue)
    {
        if (!$dateValue) return null;
        if ($dateValue instanceof \Illuminate\Support\Carbon) {
            return $dateValue->format('Y-m-d H:i:s');
        }
        if (is_numeric($dateValue) && $dateValue > 0) {
            return date('Y-m-d H:i:s', $dateValue);
        }
        return null;
    }
}