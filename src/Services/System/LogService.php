<?php

namespace roilafx\Evolutionapi\Services\System;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\EventLog;
use EvolutionCMS\Models\ManagerLog;
use EvolutionCMS\Models\User;
use Exception;

class LogService extends BaseService
{
    public function getEventLogs(array $params = [])
    {
        $query = EventLog::query();

        // Поиск по описанию или источнику
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('source', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Фильтр по типу события
        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        // Фильтр по ID события
        if (isset($params['eventid'])) {
            $query->where('eventid', $params['eventid']);
        }

        // Фильтр по типу пользователя
        if (isset($params['usertype'])) {
            $query->where('usertype', $params['usertype']);
        }

        // Фильтр по пользователю
        if (isset($params['user_id'])) {
            $query->where('user', $params['user_id']);
        }

        // Фильтр по источнику
        if (!empty($params['source'])) {
            $query->where('source', 'LIKE', "%{$params['source']}%");
        }

        // Фильтр по дате
        if (!empty($params['date_from'])) {
            $query->where('createdon', '>=', strtotime($params['date_from']));
        }

        if (!empty($params['date_to'])) {
            $query->where('createdon', '<=', strtotime($params['date_to']));
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'createdon';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findEventLog(int $id): ?EventLog
    {
        return EventLog::find($id);
    }

    public function getManagerLogs(array $params = [])
    {
        $query = ManagerLog::query();

        // Поиск по сообщению, имени элемента или имени пользователя
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('message', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('itemname', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('username', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Фильтр по действию
        if (isset($params['action'])) {
            $query->where('action', $params['action']);
        }

        // Фильтр по пользователю (internalKey)
        if (isset($params['internalKey'])) {
            $query->where('internalKey', $params['internalKey']);
        }

        // Фильтр по имени пользователя
        if (!empty($params['username'])) {
            $query->where('username', 'LIKE', "%{$params['username']}%");
        }

        // Фильтр по ID элемента
        if (!empty($params['itemid'])) {
            $query->where('itemid', $params['itemid']);
        }

        // Фильтр по дате
        if (!empty($params['date_from'])) {
            $query->where('timestamp', '>=', strtotime($params['date_from']));
        }

        if (!empty($params['date_to'])) {
            $query->where('timestamp', '<=', strtotime($params['date_to']));
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'timestamp';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findManagerLog(int $id): ?ManagerLog
    {
        return ManagerLog::find($id);
    }

    public function createEventLog(array $data): EventLog
    {
        $logData = [
            'eventid' => $data['eventid'],
            'type' => $data['type'],
            'usertype' => $data['usertype'],
            'user' => $data['user'] ?? 0,
            'source' => $data['source'],
            'description' => $data['description'],
            'createdon' => time(),
        ];

        $log = EventLog::create($logData);

        // Логирование действия менеджера
        $this->logManagerAction('event_log_create', $log->id, $data['source']);

        return $log;
    }

    public function createManagerLog(array $data): ManagerLog
    {
        $logData = [
            'internalKey' => $data['internalKey'],
            'username' => $data['username'],
            'action' => $data['action'],
            'itemid' => $data['itemid'] ?? '',
            'itemname' => $data['itemname'] ?? '',
            'message' => $data['message'],
            'ip' => $data['ip'] ?? request()->ip(),
            'useragent' => $data['useragent'] ?? request()->userAgent(),
            'timestamp' => time(),
        ];

        $log = ManagerLog::create($logData);

        // Логирование действия менеджера
        $this->logManagerAction('manager_log_create', $log->id, $data['message']);

        return $log;
    }

    public function deleteEventLog(int $id): bool
    {
        $log = $this->findEventLog($id);
        if (!$log) {
            throw new Exception('Event log not found');
        }

        $log->delete();

        $this->logManagerAction('event_log_delete', $id, 'Event log deleted');

        return true;
    }

    public function deleteManagerLog(int $id): bool
    {
        $log = $this->findManagerLog($id);
        if (!$log) {
            throw new Exception('Manager log not found');
        }

        $log->delete();

        $this->logManagerAction('manager_log_delete', $id, 'Manager log deleted');

        return true;
    }

    public function clearEventLogs(array $params = []): int
    {
        $query = EventLog::query();

        if (isset($params['older_than_days'])) {
            $cutoffTime = time() - ($params['older_than_days'] * 24 * 60 * 60);
            $query->where('createdon', '<', $cutoffTime);
        }

        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        $deletedCount = $query->delete();

        $this->logManagerAction('event_logs_clear', 0, "Cleared {$deletedCount} event logs");

        return $deletedCount;
    }

    public function clearManagerLogs(array $params = []): int
    {
        $query = ManagerLog::query();

        if (isset($params['older_than_days'])) {
            $cutoffTime = time() - ($params['older_than_days'] * 24 * 60 * 60);
            $query->where('timestamp', '<', $cutoffTime);
        }

        if (isset($params['action'])) {
            $query->where('action', $params['action']);
        }

        $deletedCount = $query->delete();

        $this->logManagerAction('manager_logs_clear', 0, "Cleared {$deletedCount} manager logs");

        return $deletedCount;
    }

    public function getEventLogStats(int $days = 30): array
    {
        $cutoffTime = time() - ($days * 24 * 60 * 60);

        return [
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
                })->toArray(),
        ];
    }

    public function getManagerLogStats(int $days = 30): array
    {
        $cutoffTime = time() - ($days * 24 * 60 * 60);

        return [
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
                })->toArray(),
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
                })->toArray(),
        ];
    }

    public function formatEventLog(EventLog $log, bool $includeUser = false): array
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

    public function formatManagerLog(ManagerLog $log, bool $includeUser = false): array
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

    protected function getEventLogTypeName(int $type): string
    {
        $types = [
            EventLog::TYPE_INFORMATION => 'information',
            EventLog::TYPE_WARNING => 'warning',
            EventLog::TYPE_ERROR => 'error',
        ];

        return $types[$type] ?? 'unknown';
    }

    protected function getUserTypeName(int $userType): string
    {
        $types = [
            EventLog::USER_MGR => 'manager',
            EventLog::USER_WEB => 'web',
        ];

        return $types[$userType] ?? 'unknown';
    }
}