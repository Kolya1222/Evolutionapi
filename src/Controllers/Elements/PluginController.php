<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Elements;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SitePluginEvent;
use EvolutionCMS\Models\Category;
use EvolutionCMS\Models\SystemEventname;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PluginController extends ApiController
{
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

            $query = SitePlugin::query();

            // Поиск по названию или описанию
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Фильтр по категории
            if ($request->has('category')) {
                $query->where('category', $validated['category']);
            }

            // Фильтр по блокировке
            if ($request->has('locked')) {
                $query->where('locked', $validated['locked']);
            }

            // Фильтр по отключению
            if ($request->has('disabled')) {
                $query->where('disabled', $validated['disabled']);
            }

            // Фильтр по типу кэширования
            if ($request->has('cache_type')) {
                $query->where('cache_type', $validated['cache_type']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCategory = $request->get('include_category', false);
            $includeEvents = $request->get('include_events', false);
            $includeAlternative = $request->get('include_alternative', false);
            
            // Форматируем данные
            $plugins = collect($paginator->items())->map(function($plugin) use ($includeCategory, $includeEvents, $includeAlternative) {
                return $this->formatPlugin($plugin, $includeCategory, $includeEvents, $includeAlternative);
            });
            
            return $this->paginated($plugins, $paginator, 'Plugins retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugins');
        }
    }

    public function show($id)
    {
        try {
            $plugin = SitePlugin::find($id);
                
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }
            
            $plugin->load('categories');
            
            $plugin->events = SitePluginEvent::where('pluginid', $id)
                ->join('system_eventnames', 'site_plugin_events.evtid', '=', 'system_eventnames.id')
                ->select('site_plugin_events.*', 'system_eventnames.name as event_name')
                ->get();
            
            $formattedPlugin = $this->formatPlugin($plugin, true, true, true);
            
            return $this->success($formattedPlugin, 'Plugin retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin');
        }
    }

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

            $pluginData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'plugincode' => $validated['plugincode'],
                'category' => $validated['category'] ?? 0,
                'editor_type' => $validated['editor_type'] ?? 0,
                'cache_type' => $validated['cache_type'] ?? false,
                'locked' => $validated['locked'] ?? false,
                'disabled' => $validated['disabled'] ?? false,
                'properties' => $validated['properties'] ?? '',
                'moduleguid' => $validated['moduleguid'] ?? '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $plugin = SitePlugin::create($pluginData);

            // Добавляем события плагина
            if (isset($validated['events']) && is_array($validated['events'])) {
                foreach ($validated['events'] as $event) {
                    SitePluginEvent::create([
                        'pluginid' => $plugin->id,
                        'evtid' => $event['evtid'],
                        'priority' => $event['priority'] ?? 0,
                    ]);
                }
            }

            $formattedPlugin = $this->formatPlugin($plugin->fresh(), true, true, false);
            
            return $this->created($formattedPlugin, 'Plugin created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create plugin');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $plugin = SitePlugin::find($id);
                
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

            $updateData = [];
            $fields = [
                'name', 'description', 'plugincode', 'category', 'editor_type',
                'cache_type', 'locked', 'disabled', 'properties', 'moduleguid'
            ];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $updateData['editedon'] = time();

            $plugin->update($updateData);

            // Обновляем события плагина
            if (isset($validated['events'])) {
                // Удаляем старые события
                SitePluginEvent::where('pluginid', $id)->delete();
                
                // Добавляем новые события
                foreach ($validated['events'] as $event) {
                    SitePluginEvent::create([
                        'pluginid' => $id,
                        'evtid' => $event['evtid'],
                        'priority' => $event['priority'] ?? 0,
                    ]);
                }
            }

            $formattedPlugin = $this->formatPlugin($plugin->fresh(), true, true, false);
            
            return $this->updated($formattedPlugin, 'Plugin updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin');
        }
    }

    public function destroy($id)
    {
        try {
            $plugin = SitePlugin::find($id);
                
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            // Удаляем связанные события
            SitePluginEvent::where('pluginid', $id)->delete();
            
            $plugin->delete();

            return $this->deleted('Plugin deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete plugin');
        }
    }

    public function duplicate($id)
    {
        try {
            $plugin = SitePlugin::with('alternative')->find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
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

            $formattedPlugin = $this->formatPlugin($newPlugin, true, true, false);
            
            return $this->created($formattedPlugin, 'Plugin duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate plugin');
        }
    }

    public function enable($id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $plugin->update([
                'disabled' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatPlugin($plugin->fresh(), true, true, true), 'Plugin enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable plugin');
        }
    }

    public function disable($id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $plugin->update([
                'disabled' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatPlugin($plugin->fresh(), true, true, true), 'Plugin disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable plugin');
        }
    }

    public function lock($id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $plugin->update([
                'locked' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatPlugin($plugin->fresh(), true, true, true), 'Plugin locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock plugin');
        }
    }

    public function unlock($id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $plugin->update([
                'locked' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatPlugin($plugin->fresh(), true, true, true), 'Plugin unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock plugin');
        }
    }

    public function content($id)
    {
        try {
            $plugin = SitePlugin::find($id);
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

    public function updateContent(Request $request, $id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $plugin->update([
                'plugincode' => $validated['content'],
                'editedon' => time(),
            ]);

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'content' => $plugin->plugincode,
            ], 'Plugin content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin content');
        }
    }

    public function events($id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $events = SitePluginEvent::where('pluginid', $id)
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
                });

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'events' => $events,
                'events_count' => $events->count(),
            ], 'Plugin events retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin events');
        }
    }

    public function addEvent(Request $request, $id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $validated = $this->validateRequest($request, [
                'event_id' => 'required|integer|exists:system_eventnames,id',
                'priority' => 'nullable|integer|min:0',
            ]);

            // Проверяем, не добавлено ли уже событие
            $existingEvent = SitePluginEvent::where('pluginid', $id)
                ->where('evtid', $validated['event_id'])
                ->first();

            if ($existingEvent) {
                return $this->error(
                    'Event already attached to plugin',
                    ['event' => 'This event is already attached to the plugin'],
                    422
                );
            }

            // Добавляем событие
            SitePluginEvent::create([
                'pluginid' => $id,
                'evtid' => $validated['event_id'],
                'priority' => $validated['priority'] ?? 0,
            ]);

            $event = SystemEventname::find($validated['event_id']);

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'priority' => $validated['priority'] ?? 0,
                ],
            ], 'Event added to plugin successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add event to plugin');
        }
    }

    public function removeEvent($id, $eventId)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $pluginEvent = SitePluginEvent::where('pluginid', $id)
                ->where('evtid', $eventId)
                ->first();

            if (!$pluginEvent) {
                return $this->notFound('Event not found in plugin');
            }

            $pluginEvent->delete();

            return $this->deleted('Event removed from plugin successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove event from plugin');
        }
    }

    public function properties($id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $properties = [];
            if (!empty($plugin->properties)) {
                $properties = $this->parseProperties($plugin->properties);
            }

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

    public function updateProperties(Request $request, $id)
    {
        try {
            $plugin = SitePlugin::find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $plugin->update([
                'properties' => $validated['properties'],
                'editedon' => time(),
            ]);

            $properties = $this->parseProperties($validated['properties']);

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'properties' => $properties,
                'properties_raw' => $plugin->properties,
            ], 'Plugin properties updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update plugin properties');
        }
    }

    public function alternative($id)
    {
        try {
            $plugin = SitePlugin::with('alternative.categories')->find($id);
            if (!$plugin) {
                return $this->notFound('Plugin not found');
            }

            $alternative = $plugin->alternative->map(function($altPlugin) {
                return $this->formatPlugin($altPlugin, true, false, false);
            });

            return $this->success([
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
                'alternative' => $alternative,
                'alternative_count' => $alternative->count(),
            ], 'Plugin alternatives retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch plugin alternatives');
        }
    }

    protected function formatPlugin($plugin, $includeCategory = false, $includeEvents = false, $includeAlternative = false)
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

        if ($includeEvents && isset($plugin->events)) {
            $data['events'] = $plugin->events->map(function($pluginEvent) {
                return [
                    'id' => $pluginEvent->evtid,
                    'name' => $pluginEvent->event_name ?? 'Unknown',
                    'priority' => $pluginEvent->priority,
                ];
            });
            $data['events_count'] = $plugin->events->count();
        }

        if ($includeAlternative && $plugin->alternative) {
            $data['alternative_count'] = $plugin->alternative->count();
        }

        return $data;
    }

    protected function parseProperties($propertiesString)
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