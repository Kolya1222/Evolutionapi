<?php

namespace roilafx\Evolutionapi\Services\Elements;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteModule;
use EvolutionCMS\Models\SiteModuleAccess;
use EvolutionCMS\Models\SiteModuleDepobj;
use Exception;

class ModuleService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SiteModule::query();

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

        // Фильтр по ресурсу
        if (isset($params['enable_resource'])) {
            $query->where('enable_resource', $params['enable_resource']);
        }

        // Фильтр по shared params
        if (isset($params['enable_sharedparams'])) {
            $query->where('enable_sharedparams', $params['enable_sharedparams']);
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?SiteModule
    {
        return SiteModule::with('categories')->find($id);
    }

    public function create(array $data): SiteModule
    {
        $mode = 'new';
        $id = 0;

        // Вызов события перед сохранением
        $eventParams = [
            'mode' => $mode,
            'id' => $id,
        ];
        $this->invokeEvent('OnBeforeModFormSave', $eventParams);

        // Проверяем, не отменило ли событие сохранение
        if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
            throw new Exception('Module creation cancelled by event');
        }

        $moduleData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'modulecode' => $data['modulecode'],
            'category' => $data['category'] ?? 0,
            'editor_type' => $data['editor_type'] ?? 0,
            'wrap' => $data['wrap'] ?? false,
            'locked' => $data['locked'] ?? false,
            'disabled' => $data['disabled'] ?? false,
            'icon' => $data['icon'] ?? '',
            'enable_resource' => $data['enable_resource'] ?? false,
            'resourcefile' => $data['resourcefile'] ?? '',
            'guid' => $data['guid'] ?? '',
            'enable_sharedparams' => $data['enable_sharedparams'] ?? false,
            'properties' => $data['properties'] ?? '',
            'createdon' => time(),
            'editedon' => time(),
        ];

        $module = SiteModule::create($moduleData);

        // Добавляем группы доступа
        if (isset($data['access_groups']) && is_array($data['access_groups'])) {
            foreach ($data['access_groups'] as $groupId) {
                SiteModuleAccess::create([
                    'module' => $module->id,
                    'usergroup' => $groupId,
                ]);
            }
        }

        // Добавляем зависимости
        if (isset($data['dependencies']) && is_array($data['dependencies'])) {
            foreach ($data['dependencies'] as $dependency) {
                SiteModuleDepobj::create([
                    'module' => $module->id,
                    'resource' => $dependency['resource'],
                    'type' => $dependency['type'],
                ]);
            }
        }

        // Вызов события после сохранения
        $this->invokeEvent('OnModFormSave', [
            'mode' => $mode,
            'id' => $module->id,
            'module' => $module
        ]);

        // Логирование действия менеджера
        $this->logManagerAction('module_create', $module->id, $module->name);

        return $module;
    }

    public function update(int $id, array $data): SiteModule
    {
        $module = $this->findById($id);
        if (!$module) {
            throw new Exception('Module not found');
        }

        // Проверяем блокировку
        if ($module->isAlreadyEdit) {
            $lockInfo = $module->alreadyEditInfo;
            throw new Exception(
                "Module is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user') . 
                " since " . date('Y-m-d H:i:s', $lockInfo['lasthit'] ?? time())
            );
        }

        // Устанавливаем блокировку перед редактированием
        $this->core->lockElement(6, $id); // 6 - тип модуля

        // Вызов события перед сохранением
        $eventParams = [
            'mode' => 'upd',
            'id' => $id,
        ];
        $this->invokeEvent('OnBeforeModFormSave', $eventParams);

        // Проверяем, не отменило ли событие сохранение
        if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
            // Снимаем блокировку если сохранение отменено
            $this->core->unlockElement(6, $id);
            throw new Exception('Module update cancelled by event');
        }

        $updateData = [];
        $fields = [
            'name', 'description', 'modulecode', 'category', 'editor_type',
            'wrap', 'locked', 'disabled', 'icon', 'enable_resource',
            'resourcefile', 'guid', 'enable_sharedparams', 'properties'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $updateData['editedon'] = time();

        $module->update($updateData);

        // Обновляем группы доступа
        if (isset($data['access_groups'])) {
            // Удаляем старые доступы
            SiteModuleAccess::where('module', $id)->delete();
            
            // Добавляем новые доступы
            foreach ($data['access_groups'] as $groupId) {
                SiteModuleAccess::create([
                    'module' => $id,
                    'usergroup' => $groupId,
                ]);
            }
        }

        // Обновляем зависимости
        if (isset($data['dependencies'])) {
            // Удаляем старые зависимости
            SiteModuleDepobj::where('module', $id)->delete();
            
            // Добавляем новые зависимости
            foreach ($data['dependencies'] as $dependency) {
                SiteModuleDepobj::create([
                    'module' => $id,
                    'resource' => $dependency['resource'],
                    'type' => $dependency['type'],
                ]);
            }
        }

        // Вызов события после сохранения
        $this->invokeEvent('OnModFormSave', [
            'mode' => 'upd',
            'id' => $module->id,
            'module' => $module
        ]);

        // Снимаем блокировку после сохранения
        $this->core->unlockElement(6, $id);

        $this->logManagerAction('module_save', $module->id, $module->name);

        return $module->fresh();
    }

    public function delete(int $id): bool
    {
        $module = $this->findById($id);
        if (!$module) {
            throw new Exception('Module not found');
        }

        // Проверяем блокировку
        if ($module->isAlreadyEdit) {
            throw new Exception('Module is locked and cannot be deleted');
        }

        // Вызов события перед удалением
        $this->invokeEvent('OnBeforeModFormDelete', [
            'id' => $module->id,
            'module' => $module
        ]);

        // Удаляем связанные доступы
        SiteModuleAccess::where('module', $id)->delete();
        
        // Удаляем связанные зависимости
        SiteModuleDepobj::where('module', $id)->delete();
        
        $module->delete();

        // Вызов события после удаления
        $this->invokeEvent('OnModFormDelete', [
            'id' => $module->id,
            'module' => $module
        ]);

        $this->logManagerAction('module_delete', $module->id, $module->name);

        return true;
    }

    public function duplicate(int $id): SiteModule
    {
        $module = $this->findById($id);
        if (!$module) {
            throw new Exception('Module not found');
        }

        // Получаем доступы модуля
        $access = SiteModuleAccess::where('module', $id)->get();
        
        // Получаем зависимости модуля
        $dependencies = SiteModuleDepobj::where('module', $id)->get();

        // Создаем копию модуля
        $newModule = $module->replicate();
        $newModule->name = $module->name . ' (Copy)';
        $newModule->createdon = time();
        $newModule->editedon = time();
        $newModule->save();

        // Копируем доступы
        foreach ($access as $accessItem) {
            SiteModuleAccess::create([
                'module' => $newModule->id,
                'usergroup' => $accessItem->usergroup,
            ]);
        }

        // Копируем зависимости
        foreach ($dependencies as $dependency) {
            SiteModuleDepobj::create([
                'module' => $newModule->id,
                'resource' => $dependency->resource,
                'type' => $dependency->type,
            ]);
        }

        $this->logManagerAction('module_duplicate', $newModule->id, $newModule->name);

        return $newModule;
    }

    public function toggleStatus(int $id, string $field, bool $value): SiteModule
    {
        $module = $this->findById($id);
        if (!$module) {
            throw new Exception('Module not found');
        }

        $module->update([
            $field => $value,
            'editedon' => time(),
        ]);

        $action = $field . '_' . ($value ? 'enable' : 'disable');
        $this->logManagerAction('module_' . $action, $module->id, $module->name);

        return $module->fresh();
    }

    public function updateContent(int $id, string $content): SiteModule
    {
        $module = $this->findById($id);
        if (!$module) {
            throw new Exception('Module not found');
        }

        // Проверяем блокировку
        if ($module->isAlreadyEdit) {
            $lockInfo = $module->alreadyEditInfo;
            throw new Exception(
                "Module is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку
        $this->core->lockElement(6, $id);

        $module->update([
            'modulecode' => $content,
            'editedon' => time(),
        ]);

        // Снимаем блокировку
        $this->core->unlockElement(6, $id);

        $this->logManagerAction('module_update_content', $module->id, $module->name);

        return $module->fresh();
    }

    public function updateProperties(int $id, string $properties): SiteModule
    {
        $module = $this->findById($id);
        if (!$module) {
            throw new Exception('Module not found');
        }

        // Проверяем блокировку
        if ($module->isAlreadyEdit) {
            $lockInfo = $module->alreadyEditInfo;
            throw new Exception(
                "Module is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку
        $this->core->lockElement(6, $id);

        $module->update([
            'properties' => $properties,
            'editedon' => time(),
        ]);

        // Снимаем блокировку
        $this->core->unlockElement(6, $id);

        $this->logManagerAction('module_update_properties', $module->id, $module->name);

        return $module->fresh();
    }

    public function getModuleAccess(int $moduleId): array
    {
        $module = $this->findById($moduleId);
        if (!$module) {
            throw new Exception('Module not found');
        }

        return SiteModuleAccess::where('module', $moduleId)
            ->get()
            ->pluck('usergroup')
            ->toArray();
    }

    public function addAccess(int $moduleId, int $usergroupId): array
    {
        $module = $this->findById($moduleId);
        if (!$module) {
            throw new Exception('Module not found');
        }

        // Проверяем, не добавлена ли уже группа
        $existingAccess = SiteModuleAccess::where('module', $moduleId)
            ->where('usergroup', $usergroupId)
            ->first();

        if ($existingAccess) {
            throw new Exception('User group already has access to module');
        }

        // Добавляем доступ
        SiteModuleAccess::create([
            'module' => $moduleId,
            'usergroup' => $usergroupId,
        ]);

        $this->logManagerAction('module_add_access', $module->id, $module->name);

        return [
            'usergroup' => $usergroupId
        ];
    }

    public function removeAccess(int $moduleId, int $usergroupId): bool
    {
        $module = $this->findById($moduleId);
        if (!$module) {
            throw new Exception('Module not found');
        }

        $access = SiteModuleAccess::where('module', $moduleId)
            ->where('usergroup', $usergroupId)
            ->first();

        if (!$access) {
            throw new Exception('Access group not found for module');
        }

        $access->delete();

        $this->logManagerAction('module_remove_access', $module->id, $module->name);

        return true;
    }

    public function getModuleDependencies(int $moduleId): array
    {
        $module = $this->findById($moduleId);
        if (!$module) {
            throw new Exception('Module not found');
        }

        return SiteModuleDepobj::where('module', $moduleId)
            ->get()
            ->map(function($dependency) {
                return [
                    'id' => $dependency->id,
                    'resource' => $dependency->resource,
                    'type' => $dependency->type,
                ];
            })
            ->toArray();
    }

    public function addDependency(int $moduleId, int $resourceId, int $type): array
    {
        $module = $this->findById($moduleId);
        if (!$module) {
            throw new Exception('Module not found');
        }

        // Проверяем, не добавлена ли уже зависимость
        $existingDependency = SiteModuleDepobj::where('module', $moduleId)
            ->where('resource', $resourceId)
            ->where('type', $type)
            ->first();

        if ($existingDependency) {
            throw new Exception('Dependency already exists');
        }

        // Добавляем зависимость
        SiteModuleDepobj::create([
            'module' => $moduleId,
            'resource' => $resourceId,
            'type' => $type,
        ]);

        $this->logManagerAction('module_add_dependency', $module->id, $module->name);

        return [
            'resource' => $resourceId,
            'type' => $type,
        ];
    }

    public function removeDependency(int $moduleId, int $dependencyId): bool
    {
        $module = $this->findById($moduleId);
        if (!$module) {
            throw new Exception('Module not found');
        }

        $dependency = SiteModuleDepobj::where('module', $moduleId)
            ->where('id', $dependencyId)
            ->first();

        if (!$dependency) {
            throw new Exception('Dependency not found for module');
        }

        $dependency->delete();

        $this->logManagerAction('module_remove_dependency', $module->id, $module->name);

        return true;
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

    public function formatModule(SiteModule $module, bool $includeCategory = false, bool $includeAccess = false, bool $includeDependencies = false): array
    {
        $data = [
            'id' => $module->id,
            'name' => $module->name,
            'description' => $module->description,
            'editor_type' => $module->editor_type,
            'wrap' => (bool)$module->wrap,
            'locked' => (bool)$module->locked,
            'disabled' => (bool)$module->disabled,
            'icon' => $module->icon,
            'enable_resource' => (bool)$module->enable_resource,
            'resourcefile' => $module->resourcefile,
            'guid' => $module->guid,
            'enable_sharedparams' => (bool)$module->enable_sharedparams,
            'created_at' => $this->safeFormatDate($module->createdon),
            'updated_at' => $this->safeFormatDate($module->editedon),
            'is_locked' => $module->isAlreadyEdit,
            'locked_info' => $module->alreadyEditInfo,
        ];

        if ($includeCategory && $module->categories) {
            $data['category'] = [
                'id' => $module->categories->id,
                'name' => $module->categories->category,
            ];
        }

        if ($includeAccess) {
            $data['access_groups'] = $this->getModuleAccess($module->id);
            $data['access_count'] = count($data['access_groups']);
        }

        if ($includeDependencies) {
            $data['dependencies'] = $this->getModuleDependencies($module->id);
            $data['dependencies_count'] = count($data['dependencies']);
        }

        return $data;
    }
}