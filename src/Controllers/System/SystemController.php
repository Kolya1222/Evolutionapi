<?php

namespace roilafx\Evolutionapi\Controllers\System;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\System\SystemService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SystemController extends ApiController
{
    protected $systemService;

    public function __construct(SystemService $systemService)
    {
        $this->systemService = $systemService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:setting_name,setting_value',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'group' => 'nullable|string|max:255',
                'include_hidden' => 'nullable|boolean',
            ]);

            $paginator = $this->systemService->getAll($validated);
            
            $settings = collect($paginator->items())->map(function($setting) {
                return $this->systemService->formatSetting($setting);
            });
            
            return $this->paginated($settings, $paginator, 'System settings retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system settings');
        }
    }

    public function show($name)
    {
        try {
            $setting = $this->systemService->findByName($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }
            
            $formattedSetting = $this->systemService->formatSetting($setting);
            
            return $this->success($formattedSetting, 'System setting retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system setting');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'setting_name' => 'required|string|max:255|unique:system_settings,setting_name',
                'setting_value' => 'required|string',
            ]);

            $setting = $this->systemService->create($validated);
            $formattedSetting = $this->systemService->formatSetting($setting);
            
            return $this->created($formattedSetting, 'System setting created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create system setting');
        }
    }

    public function update(Request $request, $name)
    {
        try {
            $setting = $this->systemService->findByName($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }

            $validated = $this->validateRequest($request, [
                'setting_value' => 'required|string',
            ]);

            $updatedSetting = $this->systemService->update($name, $validated);
            $formattedSetting = $this->systemService->formatSetting($updatedSetting);
            
            return $this->updated($formattedSetting, 'System setting updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update system setting');
        }
    }

    public function destroy($name)
    {
        try {
            $setting = $this->systemService->findByName($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }

            $this->systemService->delete($name);

            return $this->deleted('System setting deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete system setting');
        }
    }

    public function getMultiple(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'settings' => 'required|array|min:1|max:50',
                'settings.*' => 'required|string|max:255',
            ]);

            $settings = $this->systemService->getMultiple($validated['settings']);

            // Добавляем отсутствующие настройки как null
            foreach ($validated['settings'] as $settingName) {
                if (!isset($settings[$settingName])) {
                    $settings[$settingName] = null;
                }
            }

            return $this->success([
                'settings' => $settings,
                'settings_count' => count($settings),
                'found_count' => count(array_filter($settings)),
            ], 'Multiple system settings retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch multiple system settings');
        }
    }

    public function updateMultiple(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'settings' => 'required|array|min:1|max:50',
                'settings.*.name' => 'required|string|max:255',
                'settings.*.value' => 'required|string',
            ]);

            $results = $this->systemService->updateMultiple($validated['settings']);

            return $this->success([
                'results' => $results,
                'updated_count' => count($results['updated']),
                'created_count' => count($results['created']),
                'error_count' => count($results['errors']),
                'total_processed' => count($validated['settings']),
            ], 'Multiple system settings updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update multiple system settings');
        }
    }

    public function groups()
    {
        try {
            $groups = $this->systemService->getGroups();

            return $this->success([
                'groups' => $groups,
                'groups_count' => count($groups),
                'total_settings' => array_sum(array_column($groups, 'settings_count')),
            ], 'System settings groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system settings groups');
        }
    }

    public function byGroup($groupName)
    {
        try {
            $settings = $this->systemService->getByGroup($groupName);

            if (empty($settings)) {
                return $this->notFound('No settings found for the specified group');
            }

            return $this->success([
                'group' => $groupName,
                'settings' => $settings,
                'settings_count' => count($settings),
            ], 'System settings by group retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system settings by group');
        }
    }

    public function validateSettings(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'settings' => 'required|array|min:1|max:50',
                'settings.*.name' => 'required|string|max:255',
                'settings.*.value' => 'required|string',
            ]);

            $validationResults = $this->systemService->validateSettings($validated['settings']);

            $validCount = count(array_filter($validationResults, function($result) {
                return $result['valid'];
            }));
            $invalidCount = count($validationResults) - $validCount;

            return $this->success([
                'results' => $validationResults,
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'total_checked' => count($validated['settings']),
            ], 'System settings validation completed');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to validate system settings');
        }
    }
}