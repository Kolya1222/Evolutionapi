<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Elements;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SystemEventname;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SitePluginEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventController extends ApiController
{
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

            $query = SystemEventname::query();

            // Поиск по названию или группе
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('groupname', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Фильтр по сервису
            if ($request->has('service')) {
                $query->where('service', $validated['service']);
            }

            // Фильтр по группе
            if ($request->has('groupname')) {
                $query->where('groupname', $validated['groupname']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includePlugins = $request->get('include_plugins', false);
            $includePluginsCount = $request->get('include_plugins_count', false);
            
            // Форматируем данные
            $events = collect($paginator->items())->map(function($event) use ($includePlugins, $includePluginsCount) {
                return $this->formatEvent($event, $includePlugins, $includePluginsCount);
            });
            
            return $this->paginated($events, $paginator, 'Events retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch events');
        }
    }

    public function show($id)
    {
        try {
            $event = SystemEventname::find($id);
                
            if (!$event) {
                return $this->notFound('Event not found');
            }
            
            // Загружаем плагины, привязанные к событию
            $event->load(['plugins' => function($query) {
                $query->orderBy('site_plugin_events.priority', 'asc');
            }]);
            
            $formattedEvent = $this->formatEvent($event, true, true);
            
            return $this->success($formattedEvent, 'Event retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:system_eventnames,name',
                'service' => 'nullable|integer|min:0',
                'groupname' => 'nullable|string|max:255',
            ]);

            $eventData = [
                'name' => $validated['name'],
                'service' => $validated['service'] ?? 0,
                'groupname' => $validated['groupname'] ?? '',
            ];

            $event = SystemEventname::create($eventData);

            $formattedEvent = $this->formatEvent($event, false, false);
            
            return $this->created($formattedEvent, 'Event created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create event');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $event = SystemEventname::find($id);
                
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:system_eventnames,name,' . $id,
                'service' => 'nullable|integer|min:0',
                'groupname' => 'nullable|string|max:255',
            ]);

            $updateData = [];
            $fields = ['name', 'service', 'groupname'];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $event->update($updateData);

            $formattedEvent = $this->formatEvent($event->fresh(), false, false);
            
            return $this->updated($formattedEvent, 'Event updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update event');
        }
    }

    public function destroy($id)
    {
        try {
            $event = SystemEventname::find($id);
                
            if (!$event) {
                return $this->notFound('Event not found');
            }

            // Проверяем, есть ли плагины, привязанные к событию
            $pluginsCount = SitePluginEvent::where('evtid', $id)->count();
            if ($pluginsCount > 0) {
                return $this->error(
                    'Cannot delete event with attached plugins',
                    ['event' => "There are {$pluginsCount} plugins attached to this event. Remove them first."],
                    422
                );
            }

            $event->delete();

            return $this->deleted('Event deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete event');
        }
    }

    public function plugins($id)
    {
        try {
            $event = SystemEventname::find($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $plugins = SitePluginEvent::where('evtid', $id)
                ->join('site_plugins', 'site_plugin_events.pluginid', '=', 'site_plugins.id')
                ->select('site_plugin_events.*', 'site_plugins.name as plugin_name', 'site_plugins.disabled as plugin_disabled')
                ->orderBy('site_plugin_events.priority', 'asc')
                ->get()
                ->map(function($pluginEvent) {
                    return [
                        'plugin_id' => $pluginEvent->pluginid,
                        'plugin_name' => $pluginEvent->plugin_name,
                        'plugin_disabled' => (bool)$pluginEvent->plugin_disabled,
                        'priority' => $pluginEvent->priority,
                        'plugin_event_id' => $pluginEvent->id,
                    ];
                });

            return $this->success([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'plugins' => $plugins,
                'plugins_count' => $plugins->count(),
                'enabled_plugins_count' => $plugins->where('plugin_disabled', false)->count(),
                'disabled_plugins_count' => $plugins->where('plugin_disabled', true)->count(),
            ], 'Event plugins retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event plugins');
        }
    }

    public function addPlugin(Request $request, $id)
    {
        try {
            $event = SystemEventname::find($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $validated = $this->validateRequest($request, [
                'plugin_id' => 'required|integer|exists:site_plugins,id',
                'priority' => 'nullable|integer|min:0',
            ]);

            $plugin = SitePlugin::find($validated['plugin_id']);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            // Проверяем, не привязан ли уже плагин к событию
            $existingPluginEvent = SitePluginEvent::where('evtid', $id)
                ->where('pluginid', $validated['plugin_id'])
                ->first();

            if ($existingPluginEvent) {
                return $this->error(
                    'Plugin already attached to event',
                    ['plugin' => 'This plugin is already attached to the event'],
                    422
                );
            }

            // Добавляем плагин к событию
            SitePluginEvent::create([
                'evtid' => $id,
                'pluginid' => $validated['plugin_id'],
                'priority' => $validated['priority'] ?? 0,
            ]);

            return $this->success([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'plugin' => [
                    'id' => $plugin->id,
                    'name' => $plugin->name,
                    'priority' => $validated['priority'] ?? 0,
                ],
            ], 'Plugin added to event successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add plugin to event');
        }
    }

    public function removePlugin($id, $pluginId)
    {
        try {
            $event = SystemEventname::find($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $pluginEvent = SitePluginEvent::where('evtid', $id)
                ->where('pluginid', $pluginId)
                ->first();

            if (!$pluginEvent) {
                return $this->notFound('Plugin not found in event');
            }

            $pluginEvent->delete();

            return $this->deleted('Plugin removed from event successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove plugin from event');
        }
    }

    public function updatePluginPriority(Request $request, $id, $pluginId)
    {
        try {
            $event = SystemEventname::find($id);
            if (!$event) {
                return $this->notFound('Event not found');
            }

            $validated = $this->validateRequest($request, [
                'priority' => 'required|integer|min:0',
            ]);

            $pluginEvent = SitePluginEvent::where('evtid', $id)
                ->where('pluginid', $pluginId)
                ->first();

            if (!$pluginEvent) {
                return $this->notFound('Plugin not found in event');
            }

            $pluginEvent->update([
                'priority' => $validated['priority'],
            ]);

            return $this->success([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'plugin' => [
                    'id' => $pluginId,
                    'priority' => $validated['priority'],
                ],
            ], 'Plugin priority updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin priority');
        }
    }

    public function groups()
    {
        try {
            $groups = SystemEventname::select('groupname')
                ->distinct()
                ->where('groupname', '!=', '')
                ->orderBy('groupname', 'asc')
                ->pluck('groupname');

            return $this->success([
                'groups' => $groups,
                'groups_count' => $groups->count(),
            ], 'Event groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event groups');
        }
    }

    public function byGroup($groupName)
    {
        try {
            $events = SystemEventname::where('groupname', $groupName)
                ->orderBy('name', 'asc')
                ->get()
                ->map(function($event) {
                    return $this->formatEvent($event, false, true);
                });

            if ($events->isEmpty()) {
                return $this->notFound('No events found for the specified group');
            }

            return $this->success([
                'group' => $groupName,
                'events' => $events,
                'events_count' => $events->count(),
            ], 'Events by group retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch events by group');
        }
    }

    public function services()
    {
        try {
            $services = SystemEventname::select('service')
                ->distinct()
                ->orderBy('service', 'asc')
                ->pluck('service');

            return $this->success([
                'services' => $services,
                'services_count' => $services->count(),
            ], 'Event services retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch event services');
        }
    }

    public function byService($service)
    {
        try {
            $events = SystemEventname::where('service', $service)
                ->orderBy('name', 'asc')
                ->get()
                ->map(function($event) {
                    return $this->formatEvent($event, false, true);
                });

            if ($events->isEmpty()) {
                return $this->notFound('No events found for the specified service');
            }

            return $this->success([
                'service' => (int)$service,
                'events' => $events,
                'events_count' => $events->count(),
            ], 'Events by service retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch events by service');
        }
    }

    public function search(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'query' => 'required|string|min:2|max:255',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $query = $validated['query'];
            $limit = $validated['limit'] ?? 10;

            $events = SystemEventname::where('name', 'LIKE', "%{$query}%")
                ->orWhere('groupname', 'LIKE', "%{$query}%")
                ->orderBy('name', 'asc')
                ->limit($limit)
                ->get()
                ->map(function($event) {
                    return $this->formatEvent($event, false, true);
                });

            return $this->success([
                'query' => $query,
                'events' => $events,
                'events_count' => $events->count(),
            ], 'Events search completed successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to search events');
        }
    }

    protected function formatEvent($event, $includePlugins = false, $includePluginsCount = false)
    {
        $data = [
            'id' => $event->id,
            'name' => $event->name,
            'service' => $event->service,
            'groupname' => $event->groupname,
        ];

        if ($includePluginsCount) {
            $pluginsCount = SitePluginEvent::where('evtid', $event->id)->count();
            $data['plugins_count'] = $pluginsCount;
        }

        if ($includePlugins && $event->relationLoaded('plugins')) {
            $data['plugins'] = $event->plugins->map(function($plugin) {
                return [
                    'id' => $plugin->id,
                    'name' => $plugin->name,
                    'disabled' => (bool)$plugin->disabled,
                    'priority' => $plugin->pivot->priority,
                ];
            });
            $data['plugins_count'] = $event->plugins->count();
        }

        return $data;
    }
}