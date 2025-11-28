<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\EventService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventController extends ApiController
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

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