<?php

namespace EvolutionCMS\Evolutionapi\Controllers\System;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SystemController extends ApiController
{
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

            $query = SystemSetting::query();

            // Поиск по названию настройки или значению
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('setting_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('setting_value', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Фильтр по группе (по префиксу названия настройки)
            if ($request->has('group')) {
                $group = $validated['group'];
                $query->where('setting_name', 'LIKE', "{$group}_%");
            }

            // Исключение скрытых настроек (начинающихся с _)
            if (!$request->get('include_hidden', false)) {
                $query->where('setting_name', 'NOT LIKE', '\_%');
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'setting_name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 50;
            $paginator = $query->paginate($perPage);
            
            // Форматируем данные
            $settings = collect($paginator->items())->map(function($setting) {
                return $this->formatSetting($setting);
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
            $setting = SystemSetting::find($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }
            
            $formattedSetting = $this->formatSetting($setting);
            
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

            $setting = SystemSetting::create([
                'setting_name' => $validated['setting_name'],
                'setting_value' => $validated['setting_value'],
            ]);

            $formattedSetting = $this->formatSetting($setting);
            
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
            $setting = SystemSetting::find($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }

            $validated = $this->validateRequest($request, [
                'setting_value' => 'required|string',
            ]);

            $setting->update([
                'setting_value' => $validated['setting_value'],
            ]);

            $formattedSetting = $this->formatSetting($setting->fresh());
            
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
            $setting = SystemSetting::find($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }

            // Запрещаем удаление критически важных настроек
            $protectedSettings = [
                'site_start', 'error_page', 'unauthorized_page', 'site_name',
                'site_url', 'base_url', 'manager_language', 'manager_theme',
                'default_template', 'publish_default', 'search_default',
            ];

            if (in_array($name, $protectedSettings)) {
                return $this->error(
                    'Cannot delete protected system setting',
                    ['setting' => 'This system setting is protected and cannot be deleted'],
                    422
                );
            }

            $setting->delete();

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

            $settings = SystemSetting::whereIn('setting_name', $validated['settings'])
                ->get()
                ->mapWithKeys(function($setting) {
                    return [$setting->setting_name => $this->formatSetting($setting)];
                });

            // Добавляем отсутствующие настройки как null
            foreach ($validated['settings'] as $settingName) {
                if (!$settings->has($settingName)) {
                    $settings[$settingName] = null;
                }
            }

            return $this->success([
                'settings' => $settings,
                'settings_count' => $settings->count(),
                'found_count' => $settings->whereNotNull()->count(),
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

            $results = [];
            $updatedCount = 0;
            $createdCount = 0;

            foreach ($validated['settings'] as $settingData) {
                $setting = SystemSetting::find($settingData['name']);
                
                if ($setting) {
                    // Обновляем существующую настройку
                    $setting->update(['setting_value' => $settingData['value']]);
                    $updatedCount++;
                    $results[$settingData['name']] = 'updated';
                } else {
                    // Создаем новую настройку
                    SystemSetting::create([
                        'setting_name' => $settingData['name'],
                        'setting_value' => $settingData['value'],
                    ]);
                    $createdCount++;
                    $results[$settingData['name']] = 'created';
                }
            }

            return $this->success([
                'results' => $results,
                'updated_count' => $updatedCount,
                'created_count' => $createdCount,
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
            // Получаем все настройки и группируем по префиксам
            $settings = SystemSetting::where('setting_name', 'NOT LIKE', '\_%')
                ->orderBy('setting_name', 'asc')
                ->get();

            $groups = $settings->groupBy(function($setting) {
                // Разделяем название настройки по подчеркиваниям
                $parts = explode('_', $setting->setting_name);
                return count($parts) > 1 ? $parts[0] : 'general';
            })->map(function($groupSettings, $groupName) {
                return [
                    'name' => $groupName,
                    'settings_count' => $groupSettings->count(),
                    'settings' => $groupSettings->map(function($setting) {
                        return $this->formatSetting($setting);
                    }),
                ];
            });

            return $this->success([
                'groups' => $groups,
                'groups_count' => $groups->count(),
                'total_settings' => $settings->count(),
            ], 'System settings groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system settings groups');
        }
    }

    public function byGroup($groupName)
    {
        try {
            $settings = SystemSetting::where('setting_name', 'LIKE', "{$groupName}\_%")
                ->orWhere('setting_name', $groupName)
                ->orderBy('setting_name', 'asc')
                ->get()
                ->map(function($setting) {
                    return $this->formatSetting($setting);
                });

            if ($settings->isEmpty()) {
                return $this->notFound('No settings found for the specified group');
            }

            return $this->success([
                'group' => $groupName,
                'settings' => $settings,
                'settings_count' => $settings->count(),
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

            $validationResults = [];
            $validCount = 0;
            $invalidCount = 0;

            foreach ($validated['settings'] as $settingData) {
                $validationResult = $this->validateSettingValue($settingData['name'], $settingData['value']);
                
                $validationResults[$settingData['name']] = [
                    'valid' => $validationResult['valid'],
                    'message' => $validationResult['message'],
                    'current_value' => $this->getCurrentValue($settingData['name']),
                ];

                if ($validationResult['valid']) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
            }

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
    
    protected function formatSetting($setting)
    {
        return [
            'name' => $setting->setting_name,
            'value' => $setting->setting_value,
            'is_serialized' => $this->isSerialized($setting->setting_value),
            'parsed_value' => $this->parseSettingValue($setting->setting_value),
            'group' => $this->getSettingGroup($setting->setting_name),
        ];
    }

    protected function isSerialized($value)
    {
        if (!is_string($value)) {
            return false;
        }
        
        return @unserialize($value) !== false;
    }

    protected function parseSettingValue($value)
    {
        // Пытаемся разобрать сериализованные данные
        if ($this->isSerialized($value)) {
            return @unserialize($value);
        }

        // Пытаемся разобрать JSON
        if ($this->isJson($value)) {
            return json_decode($value, true);
        }

        // Пытаемся преобразовать в число если возможно
        if (is_numeric($value)) {
            return $value + 0; // Преобразует в int или float
        }

        // Пытаемся преобразовать в boolean
        if ($value === 'true' || $value === 'false') {
            return $value === 'true';
        }

        return $value;
    }

    protected function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function getSettingGroup($settingName)
    {
        $parts = explode('_', $settingName);
        return count($parts) > 1 ? $parts[0] : 'general';
    }

    protected function validateSettingValue($settingName, $value)
    {
        $validations = [
            'site_name' => ['required', 'string', 'max:255'],
            'site_url' => ['required', 'url'],
            'base_url' => ['required', 'string'],
            'manager_language' => ['required', 'string', 'max:10'],
            'default_template' => ['required', 'integer'],
            'publish_default' => ['required', 'boolean'],
            'cache_default' => ['required', 'boolean'],
            'search_default' => ['required', 'boolean'],
            'friendly_urls' => ['required', 'boolean'],
            'use_alias_path' => ['required', 'boolean'],
            'use_udperms' => ['required', 'boolean'],
        ];

        if (isset($validations[$settingName])) {
            // Здесь можно добавить более сложную логику валидации
            return ['valid' => true, 'message' => 'Setting value is valid'];
        }

        return ['valid' => true, 'message' => 'No specific validation for this setting'];
    }

    protected function getCurrentValue($settingName)
    {
        $setting = SystemSetting::find($settingName);
        return $setting ? $setting->setting_value : null;
    }
}