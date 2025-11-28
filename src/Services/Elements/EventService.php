<?php

namespace roilafx\Evolutionapi\Services\Elements;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SystemEventname;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SitePluginEvent;
use Exception;

class EventService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SystemEventname::query();

        // Поиск по названию или группе
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('groupname', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Фильтр по сервису
        if (isset($params['service'])) {
            $query->where('service', $params['service']);
        }

        // Фильтр по группе
        if (!empty($params['groupname'])) {
            $query->where('groupname', $params['groupname']);
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?SystemEventname
    {
        return SystemEventname::with(['plugins' => function($query) {
            $query->orderBy('site_plugin_events.priority', 'asc');
        }])->find($id);
    }

    public function create(array $data): SystemEventname
    {
        $eventData = [
            'name' => $data['name'],
            'service' => $data['service'] ?? 0,
            'groupname' => $data['groupname'] ?? '',
        ];

        $event = SystemEventname::create($eventData);

        // Логирование действия менеджера
        $this->logManagerAction('event_create', $event->id, $event->name);

        return $event;
    }

    public function update(int $id, array $data): SystemEventname
    {
        $event = $this->findById($id);
        if (!$event) {
            throw new Exception('Event not found');
        }

        $updateData = [];
        $fields = ['name', 'service', 'groupname'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $event->update($updateData);

        $this->logManagerAction('event_save', $event->id, $event->name);

        return $event->fresh();
    }

    public function delete(int $id): bool
    {
        $event = $this->findById($id);
        if (!$event) {
            throw new Exception('Event not found');
        }

        // Проверяем, есть ли плагины, привязанные к событию
        $pluginsCount = SitePluginEvent::where('evtid', $id)->count();
        if ($pluginsCount > 0) {
            throw new Exception("Cannot delete event with {$pluginsCount} attached plugins. Remove them first.");
        }

        $event->delete();

        $this->logManagerAction('event_delete', $event->id, $event->name);

        return true;
    }

    public function getEventPlugins(int $eventId): array
    {
        $event = $this->findById($eventId);
        if (!$event) {
            throw new Exception('Event not found');
        }

        return SitePluginEvent::where('evtid', $eventId)
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
            })
            ->toArray();
    }

    public function addPluginToEvent(int $eventId, int $pluginId, int $priority = 0): array
    {
        $event = $this->findById($eventId);
        if (!$event) {
            throw new Exception('Event not found');
        }

        $plugin = SitePlugin::find($pluginId);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        // Проверяем, не привязан ли уже плагин к событию
        $existingPluginEvent = SitePluginEvent::where('evtid', $eventId)
            ->where('pluginid', $pluginId)
            ->first();

        if ($existingPluginEvent) {
            throw new Exception('Plugin already attached to event');
        }

        // Добавляем плагин к событию
        $pluginEvent = SitePluginEvent::create([
            'evtid' => $eventId,
            'pluginid' => $pluginId,
            'priority' => $priority,
        ]);

        $this->logManagerAction('event_add_plugin', $event->id, $event->name);

        return [
            'plugin_event' => $pluginEvent,
            'plugin' => $plugin,
        ];
    }

    public function removePluginFromEvent(int $eventId, int $pluginId): bool
    {
        $event = $this->findById($eventId);
        if (!$event) {
            throw new Exception('Event not found');
        }

        $pluginEvent = SitePluginEvent::where('evtid', $eventId)
            ->where('pluginid', $pluginId)
            ->first();

        if (!$pluginEvent) {
            throw new Exception('Plugin not found in event');
        }

        $pluginEvent->delete();

        $this->logManagerAction('event_remove_plugin', $event->id, $event->name);

        return true;
    }

    public function updatePluginPriority(int $eventId, int $pluginId, int $priority): array
    {
        $event = $this->findById($eventId);
        if (!$event) {
            throw new Exception('Event not found');
        }

        $pluginEvent = SitePluginEvent::where('evtid', $eventId)
            ->where('pluginid', $pluginId)
            ->first();

        if (!$pluginEvent) {
            throw new Exception('Plugin not found in event');
        }

        $pluginEvent->update([
            'priority' => $priority,
        ]);

        $this->logManagerAction('event_update_plugin_priority', $event->id, $event->name);

        return [
            'plugin_event' => $pluginEvent,
            'plugin' => SitePlugin::find($pluginId),
        ];
    }

    public function getEventGroups(): array
    {
        return SystemEventname::select('groupname')
            ->distinct()
            ->where('groupname', '!=', '')
            ->orderBy('groupname', 'asc')
            ->pluck('groupname')
            ->toArray();
    }

    public function getEventsByGroup(string $groupName): array
    {
        return SystemEventname::where('groupname', $groupName)
            ->orderBy('name', 'asc')
            ->get()
            ->map(function($event) {
                return $this->formatEvent($event, false, true);
            })
            ->toArray();
    }

    public function getEventServices(): array
    {
        return SystemEventname::select('service')
            ->distinct()
            ->orderBy('service', 'asc')
            ->pluck('service')
            ->toArray();
    }

    public function getEventsByService(int $service): array
    {
        return SystemEventname::where('service', $service)
            ->orderBy('name', 'asc')
            ->get()
            ->map(function($event) {
                return $this->formatEvent($event, false, true);
            })
            ->toArray();
    }

    public function searchEvents(string $query, int $limit = 10): array
    {
        return SystemEventname::where('name', 'LIKE', "%{$query}%")
            ->orWhere('groupname', 'LIKE', "%{$query}%")
            ->orderBy('name', 'asc')
            ->limit($limit)
            ->get()
            ->map(function($event) {
                return $this->formatEvent($event, false, true);
            })
            ->toArray();
    }

    public function formatEvent(SystemEventname $event, bool $includePlugins = false, bool $includePluginsCount = false): array
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
            })->toArray();
            $data['plugins_count'] = count($data['plugins']);
        }

        return $data;
    }
}