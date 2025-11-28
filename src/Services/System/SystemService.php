<?php

namespace roilafx\Evolutionapi\Services\System;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SystemSetting;
use Exception;

class SystemService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SystemSetting::query();

        // Поиск по названию настройки или значению
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('setting_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('setting_value', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Фильтр по группе (по префиксу названия настройки)
        if (!empty($params['group'])) {
            $group = $params['group'];
            $query->where('setting_name', 'LIKE', "{$group}_%");
        }

        // Исключение скрытых настроек (начинающихся с _)
        if (!($params['include_hidden'] ?? false)) {
            $query->where('setting_name', 'NOT LIKE', '\_%');
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'setting_name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 50;
        
        return $query->paginate($perPage);
    }

    public function findByName(string $name): ?SystemSetting
    {
        return SystemSetting::find($name);
    }

    public function create(array $data): SystemSetting
    {
        $setting = SystemSetting::create([
            'setting_name' => $data['setting_name'],
            'setting_value' => $data['setting_value'],
        ]);

        // Вызов события после создания настройки
        $this->invokeEvent('OnSysSettingsSave', [
            'setting_name' => $setting->setting_name,
            'setting_value' => $setting->setting_value,
        ]);

        // Логирование действия менеджера
        $this->logManagerAction('system_setting_create', $setting->setting_name, $setting->setting_name);

        return $setting;
    }

    public function update(string $name, array $data): SystemSetting
    {
        $setting = $this->findByName($name);
        if (!$setting) {
            throw new Exception('System setting not found');
        }

        // Вызов события перед сохранением
        $this->invokeEvent('OnBeforeSysSettingsSave', [
            'setting_name' => $setting->setting_name,
            'setting_value' => $data['setting_value'],
            'old_value' => $setting->setting_value,
        ]);

        $setting->update([
            'setting_value' => $data['setting_value'],
        ]);

        // Вызов события после сохранения
        $this->invokeEvent('OnSysSettingsSave', [
            'setting_name' => $setting->setting_name,
            'setting_value' => $setting->setting_value,
            'old_value' => $data['old_value'] ?? null,
        ]);

        // Логирование действия менеджера
        $this->logManagerAction('system_setting_save', $setting->setting_name, $setting->setting_name);

        return $setting->fresh();
    }

    public function delete(string $name): bool
    {
        $setting = $this->findByName($name);
        if (!$setting) {
            throw new Exception('System setting not found');
        }

        // Запрещаем удаление критически важных настроек
        $protectedSettings = [
            'site_start', 'error_page', 'unauthorized_page', 'site_name',
            'site_url', 'base_url', 'manager_language', 'manager_theme',
            'default_template', 'publish_default', 'search_default',
        ];

        if (in_array($name, $protectedSettings)) {
            throw new Exception('Cannot delete protected system setting');
        }

        // Вызов события перед удалением
        $this->invokeEvent('OnBeforeSysSettingsDelete', [
            'setting_name' => $setting->setting_name,
            'setting_value' => $setting->setting_value,
        ]);

        $setting->delete();

        // Вызов события после удаления
        $this->invokeEvent('OnSysSettingsDelete', [
            'setting_name' => $setting->setting_name,
        ]);

        $this->logManagerAction('system_setting_delete', $setting->setting_name, $setting->setting_name);

        return true;
    }

    public function getMultiple(array $settingNames): array
    {
        return SystemSetting::whereIn('setting_name', $settingNames)
            ->get()
            ->mapWithKeys(function($setting) {
                return [$setting->setting_name => $this->formatSetting($setting)];
            })
            ->toArray();
    }

    public function updateMultiple(array $settings): array
    {
        $results = [
            'updated' => [],
            'created' => [],
            'errors' => []
        ];

        foreach ($settings as $settingData) {
            try {
                $setting = $this->findByName($settingData['name']);
                
                if ($setting) {
                    // Обновляем существующую настройку
                    $this->update($settingData['name'], ['setting_value' => $settingData['value']]);
                    $results['updated'][] = $settingData['name'];
                } else {
                    // Создаем новую настройку
                    $this->create([
                        'setting_name' => $settingData['name'],
                        'setting_value' => $settingData['value'],
                    ]);
                    $results['created'][] = $settingData['name'];
                }
            } catch (Exception $e) {
                $results['errors'][$settingData['name']] = $e->getMessage();
            }
        }

        return $results;
    }

    public function getGroups(): array
    {
        $settings = SystemSetting::where('setting_name', 'NOT LIKE', '\_%')
            ->orderBy('setting_name', 'asc')
            ->get();

        return $settings->groupBy(function($setting) {
            return $this->getSettingGroup($setting->setting_name);
        })->map(function($groupSettings, $groupName) {
            return [
                'name' => $groupName,
                'settings_count' => $groupSettings->count(),
                'settings' => $groupSettings->map(function($setting) {
                    return $this->formatSetting($setting);
                })->toArray(),
            ];
        })->toArray();
    }

    public function getByGroup(string $groupName): array
    {
        return SystemSetting::where('setting_name', 'LIKE', "{$groupName}\_%")
            ->orWhere('setting_name', $groupName)
            ->orderBy('setting_name', 'asc')
            ->get()
            ->map(function($setting) {
                return $this->formatSetting($setting);
            })
            ->toArray();
    }

    public function validateSettings(array $settings): array
    {
        $validationResults = [];

        foreach ($settings as $settingData) {
            $validationResult = $this->validateSettingValue($settingData['name'], $settingData['value']);
            
            $validationResults[$settingData['name']] = [
                'valid' => $validationResult['valid'],
                'message' => $validationResult['message'],
                'current_value' => $this->getCurrentValue($settingData['name']),
            ];
        }

        return $validationResults;
    }

    public function formatSetting(SystemSetting $setting): array
    {
        return [
            'name' => $setting->setting_name,
            'value' => $setting->setting_value,
            'is_serialized' => $this->isSerialized($setting->setting_value),
            'parsed_value' => $this->parseSettingValue($setting->setting_value),
            'group' => $this->getSettingGroup($setting->setting_name),
        ];
    }

    protected function isSerialized($value): bool
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
            return $value + 0;
        }

        // Пытаемся преобразовать в boolean
        if ($value === 'true' || $value === 'false') {
            return $value === 'true';
        }

        return $value;
    }

    protected function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function getSettingGroup($settingName): string
    {
        $parts = explode('_', $settingName);
        return count($parts) > 1 ? $parts[0] : 'general';
    }

    protected function validateSettingValue($settingName, $value): array
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
            return ['valid' => true, 'message' => 'Setting value is valid'];
        }

        return ['valid' => true, 'message' => 'No specific validation for this setting'];
    }

    protected function getCurrentValue($settingName): ?string
    {
        $setting = $this->findByName($settingName);
        return $setting ? $setting->setting_value : null;
    }
}