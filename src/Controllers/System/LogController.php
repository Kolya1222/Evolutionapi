<?php

namespace roilafx\Evolutionapi\Controllers\System;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\System\LogService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LogController extends ApiController
{
    protected $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

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