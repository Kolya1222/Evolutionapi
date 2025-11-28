<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\PluginService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PluginController extends ApiController
{
    protected $pluginService;

    public function __construct(PluginService $pluginService)
    {
        $this->pluginService = $pluginService;
    }

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