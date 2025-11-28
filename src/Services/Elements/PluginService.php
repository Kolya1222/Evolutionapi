<?php

namespace roilafx\Evolutionapi\Services\Elements;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SitePluginEvent;
use EvolutionCMS\Models\SystemEventname;
use Exception;

class PluginService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SitePlugin::query();

        // Поиск по названию или описанию
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Фильтр по категории
        if (!empty($params['category'])) {
            $query->where('category', $params['category']);
        }

        // Фильтр по блокировке
        if (isset($params['locked'])) {
            $query->where('locked', $params['locked']);
        }

        // Фильтр по отключению
        if (isset($params['disabled'])) {
            $query->where('disabled', $params['disabled']);
        }

        // Фильтр по типу кэширования
        if (isset($params['cache_type'])) {
            $query->where('cache_type', $params['cache_type']);
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?SitePlugin
    {
        return SitePlugin::with('categories')->find($id);
    }

    public function create(array $data): SitePlugin
    {
        $mode = 'new';
        $id = 0;

        // Сохраняем оригинальный $_POST и временно подменяем
        $originalPost = $_POST;
        $_POST = $data;

        try {
            // Вызов события перед сохранением с правильными параметрами
            $eventParams = [
                'mode' => $mode,
                'id' => $id,
            ];
            $this->invokeEvent('OnBeforePluginFormSave', $eventParams);

            // Проверяем, не отменило ли событие сохранение
            if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
                throw new Exception('Plugin creation cancelled by event');
            }

            $pluginData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'plugincode' => $data['plugincode'],
                'category' => $data['category'] ?? 0,
                'editor_type' => $data['editor_type'] ?? 0,
                'cache_type' => $data['cache_type'] ?? false,
                'locked' => $data['locked'] ?? false,
                'disabled' => $data['disabled'] ?? false,
                'properties' => $data['properties'] ?? '',
                'moduleguid' => $data['moduleguid'] ?? '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $plugin = SitePlugin::create($pluginData);

            // Добавляем события плагина
            if (isset($data['events']) && is_array($data['events'])) {
                foreach ($data['events'] as $event) {
                    SitePluginEvent::create([
                        'pluginid' => $plugin->id,
                        'evtid' => $event['evtid'],
                        'priority' => $event['priority'] ?? 0,
                    ]);
                }
            }

            // Вызов события после сохранения
            $this->invokeEvent('OnPluginFormSave', [
                'mode' => $mode,
                'id' => $plugin->id,
                'plugin' => $plugin
            ]);

            // Логирование действия менеджера
            $this->logManagerAction('plugin_create', $plugin->id, $plugin->name);

            return $plugin;
        } finally {
            // Восстанавливаем оригинальный $_POST
            $_POST = $originalPost;
        }
    }

    public function update(int $id, array $data): SitePlugin
    {
        $plugin = $this->findById($id);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        // Проверяем блокировку
        if ($plugin->isAlreadyEdit) {
            $lockInfo = $plugin->alreadyEditInfo;
            throw new Exception(
                "Plugin is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку перед редактированием
        $this->core->lockElement(5, $id); // 5 - тип плагина

        $mode = 'upd';

        // Сохраняем оригинальный $_POST и временно подменяем
        $originalPost = $_POST;
        $_POST = $data;

        try {
            // Вызов события перед сохранением
            $eventParams = [
                'mode' => $mode,
                'id' => $id,
            ];
            $this->invokeEvent('OnBeforePluginFormSave', $eventParams);

            // Проверяем, не отменило ли событие сохранение
            if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
                $this->core->unlockElement(5, $id);
                throw new Exception('Plugin update cancelled by event');
            }

            $updateData = [];
            $fields = [
                'name', 'description', 'plugincode', 'category', 'editor_type',
                'cache_type', 'locked', 'disabled', 'properties', 'moduleguid'
            ];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            $updateData['editedon'] = time();

            $plugin->update($updateData);

            // Обновляем события плагина
            if (isset($data['events'])) {
                // Удаляем старые события
                SitePluginEvent::where('pluginid', $id)->delete();
                
                // Добавляем новые события
                foreach ($data['events'] as $event) {
                    SitePluginEvent::create([
                        'pluginid' => $id,
                        'evtid' => $event['evtid'],
                        'priority' => $event['priority'] ?? 0,
                    ]);
                }
            }

            // Вызов события после сохранения
            $this->invokeEvent('OnPluginFormSave', [
                'mode' => $mode,
                'id' => $plugin->id,
                'plugin' => $plugin
            ]);

            $this->logManagerAction('plugin_save', $plugin->id, $plugin->name);

            return $plugin->fresh();
        } finally {
            // Восстанавливаем оригинальный $_POST и снимаем блокировку
            $_POST = $originalPost;
            $this->core->unlockElement(5, $id);
        }
    }

    public function delete(int $id): bool
    {
        $plugin = $this->findById($id);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        // Проверяем блокировку
        if ($plugin->isAlreadyEdit) {
            throw new Exception('Plugin is locked and cannot be deleted');
        }

        // Вызов события перед удалением
        $this->invokeEvent('OnBeforePluginFormDelete', [
            'id' => $plugin->id,
            'plugin' => $plugin
        ]);

        // Удаляем связанные события
        SitePluginEvent::where('pluginid', $id)->delete();
        
        $plugin->delete();

        // Вызов события после удаления
        $this->invokeEvent('OnPluginFormDelete', [
            'id' => $plugin->id,
            'plugin' => $plugin
        ]);

        $this->logManagerAction('plugin_delete', $plugin->id, $plugin->name);

        return true;
    }

    public function duplicate(int $id): SitePlugin
    {
        $plugin = SitePlugin::with('alternative')->find($id);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        // Получаем события плагина
        $events = SitePluginEvent::where('pluginid', $id)->get();

        // Создаем копию плагина
        $newPlugin = $plugin->replicate();
        $newPlugin->name = $plugin->name . ' (Copy)';
        $newPlugin->createdon = time();
        $newPlugin->editedon = time();
        $newPlugin->save();

        // Копируем события
        foreach ($events as $event) {
            SitePluginEvent::create([
                'pluginid' => $newPlugin->id,
                'evtid' => $event->evtid,
                'priority' => $event->priority,
            ]);
        }

        $this->logManagerAction('plugin_duplicate', $newPlugin->id, $newPlugin->name);

        return $newPlugin;
    }

    public function toggleStatus(int $id, string $field, bool $value): SitePlugin
    {
        $plugin = $this->findById($id);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        $plugin->update([
            $field => $value,
            'editedon' => time(),
        ]);

        $action = $field . '_' . ($value ? 'enable' : 'disable');
        $this->logManagerAction('plugin_' . $action, $plugin->id, $plugin->name);

        return $plugin->fresh();
    }

    public function updateContent(int $id, string $content): SitePlugin
    {
        $plugin = $this->findById($id);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        // Проверяем блокировку
        if ($plugin->isAlreadyEdit) {
            $lockInfo = $plugin->alreadyEditInfo;
            throw new Exception(
                "Plugin is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку
        $this->core->lockElement(5, $id);

        $plugin->update([
            'plugincode' => $content,
            'editedon' => time(),
        ]);

        // Снимаем блокировку
        $this->core->unlockElement(5, $id);

        $this->logManagerAction('plugin_update_content', $plugin->id, $plugin->name);

        return $plugin->fresh();
    }

    public function updateProperties(int $id, string $properties): SitePlugin
    {
        $plugin = $this->findById($id);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        // Проверяем блокировку
        if ($plugin->isAlreadyEdit) {
            $lockInfo = $plugin->alreadyEditInfo;
            throw new Exception(
                "Plugin is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку
        $this->core->lockElement(5, $id);

        $plugin->update([
            'properties' => $properties,
            'editedon' => time(),
        ]);

        // Снимаем блокировку
        $this->core->unlockElement(5, $id);

        $this->logManagerAction('plugin_update_properties', $plugin->id, $plugin->name);

        return $plugin->fresh();
    }

    public function getPluginEvents(int $pluginId): array
    {
        $plugin = $this->findById($pluginId);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        return SitePluginEvent::where('pluginid', $pluginId)
            ->join('system_eventnames', 'site_plugin_events.evtid', '=', 'system_eventnames.id')
            ->select('site_plugin_events.*', 'system_eventnames.name as event_name')
            ->get()
            ->map(function($pluginEvent) {
                return [
                    'id' => $pluginEvent->evtid,
                    'name' => $pluginEvent->event_name ?? 'Unknown',
                    'priority' => $pluginEvent->priority,
                    'plugin_event_id' => $pluginEvent->id,
                ];
            })
            ->toArray();
    }

    public function addEvent(int $pluginId, int $eventId, int $priority = 0): array
    {
        $plugin = $this->findById($pluginId);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        // Проверяем существование события
        $event = SystemEventname::find($eventId);
        if (!$event) {
            throw new Exception('Event not found');
        }

        // Проверяем, не добавлено ли уже событие
        $existingEvent = SitePluginEvent::where('pluginid', $pluginId)
            ->where('evtid', $eventId)
            ->first();

        if ($existingEvent) {
            throw new Exception('Event already attached to plugin');
        }

        // Добавляем событие
        SitePluginEvent::create([
            'pluginid' => $pluginId,
            'evtid' => $eventId,
            'priority' => $priority,
        ]);

        $this->logManagerAction('plugin_add_event', $plugin->id, $plugin->name);

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'priority' => $priority,
            ]
        ];
    }

    public function removeEvent(int $pluginId, int $eventId): bool
    {
        $plugin = $this->findById($pluginId);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        $pluginEvent = SitePluginEvent::where('pluginid', $pluginId)
            ->where('evtid', $eventId)
            ->first();

        if (!$pluginEvent) {
            throw new Exception('Event not found in plugin');
        }

        $pluginEvent->delete();

        $this->logManagerAction('plugin_remove_event', $plugin->id, $plugin->name);

        return true;
    }

    public function getAlternativePlugins(int $pluginId): array
    {
        $plugin = SitePlugin::with('alternative.categories')->find($pluginId);
        if (!$plugin) {
            throw new Exception('Plugin not found');
        }

        return $plugin->alternative->map(function($altPlugin) {
            return $this->formatPlugin($altPlugin, true, false, false);
        })->toArray();
    }

    public function parseProperties(string $propertiesString): array
    {
        if (empty($propertiesString)) {
            return [];
        }

        $properties = [];
        $lines = explode("\n", $propertiesString);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $properties[trim($key)] = trim($value);
            }
        }
        
        return $properties;
    }

    public function formatPlugin(SitePlugin $plugin, bool $includeCategory = false, bool $includeEvents = false, bool $includeAlternative = false): array
    {
        $data = [
            'id' => $plugin->id,
            'name' => $plugin->name,
            'description' => $plugin->description,
            'editor_type' => $plugin->editor_type,
            'cache_type' => (bool)$plugin->cache_type,
            'locked' => (bool)$plugin->locked,
            'disabled' => (bool)$plugin->disabled,
            'created_at' => $this->safeFormatDate($plugin->createdon),
            'updated_at' => $this->safeFormatDate($plugin->editedon),
            'is_locked' => $plugin->isAlreadyEdit,
            'locked_info' => $plugin->alreadyEditInfo,
        ];

        if ($includeCategory && $plugin->categories) {
            $data['category'] = [
                'id' => $plugin->categories->id,
                'name' => $plugin->categories->category,
            ];
        }

        if ($includeEvents) {
            $data['events'] = $this->getPluginEvents($plugin->id);
            $data['events_count'] = count($data['events']);
        }

        if ($includeAlternative) {
            $data['alternative_count'] = $plugin->alternative->count();
        }

        return $data;
    }
}