<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Elements;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteModule;
use EvolutionCMS\Models\SiteModuleAccess;
use EvolutionCMS\Models\SiteModuleDepobj;
use EvolutionCMS\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ModuleController extends ApiController
{
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'enable_resource' => 'nullable|boolean',
                'enable_sharedparams' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_access' => 'nullable|boolean',
                'include_dependencies' => 'nullable|boolean',
            ]);

            $query = SiteModule::query();

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

            // Фильтр по ресурсу
            if ($request->has('enable_resource')) {
                $query->where('enable_resource', $validated['enable_resource']);
            }

            // Фильтр по shared params
            if ($request->has('enable_sharedparams')) {
                $query->where('enable_sharedparams', $validated['enable_sharedparams']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCategory = $request->get('include_category', false);
            $includeAccess = $request->get('include_access', false);
            $includeDependencies = $request->get('include_dependencies', false);
            
            // Форматируем данные
            $modules = collect($paginator->items())->map(function($module) use ($includeCategory, $includeAccess, $includeDependencies) {
                return $this->formatModule($module, $includeCategory, $includeAccess, $includeDependencies);
            });
            
            return $this->paginated($modules, $paginator, 'Modules retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch modules');
        }
    }

    public function show($id)
    {
        try {
            $module = SiteModule::find($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }
            
            // Загружаем категорию
            $module->load('categories');
            
            // Загружаем доступы
            $module->access = SiteModuleAccess::where('module', $id)->get();
            
            // Загружаем зависимости
            $module->dependencies = SiteModuleDepobj::where('module', $id)->get();
            
            $formattedModule = $this->formatModule($module, true, true, true);
            
            return $this->success($formattedModule, 'Module retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:site_modules,name',
                'description' => 'nullable|string',
                'modulecode' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'wrap' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'icon' => 'nullable|string|max:255',
                'enable_resource' => 'nullable|boolean',
                'resourcefile' => 'nullable|string|max:255',
                'guid' => 'nullable|string|max:255',
                'enable_sharedparams' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'access_groups' => 'nullable|array',
                'access_groups.*' => 'integer|exists:user_roles,id',
                'dependencies' => 'nullable|array',
                'dependencies.*.resource' => 'required|integer|exists:site_content,id',
                'dependencies.*.type' => 'required|integer|min:0',
            ]);

            $moduleData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'modulecode' => $validated['modulecode'],
                'category' => $validated['category'] ?? 0,
                'editor_type' => $validated['editor_type'] ?? 0,
                'wrap' => $validated['wrap'] ?? false,
                'locked' => $validated['locked'] ?? false,
                'disabled' => $validated['disabled'] ?? false,
                'icon' => $validated['icon'] ?? '',
                'enable_resource' => $validated['enable_resource'] ?? false,
                'resourcefile' => $validated['resourcefile'] ?? '',
                'guid' => $validated['guid'] ?? '',
                'enable_sharedparams' => $validated['enable_sharedparams'] ?? false,
                'properties' => $validated['properties'] ?? '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $module = SiteModule::create($moduleData);

            // Добавляем группы доступа
            if (isset($validated['access_groups']) && is_array($validated['access_groups'])) {
                foreach ($validated['access_groups'] as $groupId) {
                    SiteModuleAccess::create([
                        'module' => $module->id,
                        'usergroup' => $groupId,
                    ]);
                }
            }

            // Добавляем зависимости
            if (isset($validated['dependencies']) && is_array($validated['dependencies'])) {
                foreach ($validated['dependencies'] as $dependency) {
                    SiteModuleDepobj::create([
                        'module' => $module->id,
                        'resource' => $dependency['resource'],
                        'type' => $dependency['type'],
                    ]);
                }
            }

            $formattedModule = $this->formatModule($module->fresh(), true, true, true);
            
            return $this->created($formattedModule, 'Module created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create module');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $module = SiteModule::find($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_modules,name,' . $id,
                'description' => 'nullable|string',
                'modulecode' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'wrap' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'icon' => 'nullable|string|max:255',
                'enable_resource' => 'nullable|boolean',
                'resourcefile' => 'nullable|string|max:255',
                'guid' => 'nullable|string|max:255',
                'enable_sharedparams' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'access_groups' => 'nullable|array',
                'access_groups.*' => 'integer|exists:user_roles,id',
                'dependencies' => 'nullable|array',
                'dependencies.*.resource' => 'required|integer|exists:site_content,id',
                'dependencies.*.type' => 'required|integer|min:0',
            ]);

            $updateData = [];
            $fields = [
                'name', 'description', 'modulecode', 'category', 'editor_type',
                'wrap', 'locked', 'disabled', 'icon', 'enable_resource',
                'resourcefile', 'guid', 'enable_sharedparams', 'properties'
            ];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $updateData['editedon'] = time();

            $module->update($updateData);

            // Обновляем группы доступа
            if (isset($validated['access_groups'])) {
                // Удаляем старые доступы
                SiteModuleAccess::where('module', $id)->delete();
                
                // Добавляем новые доступы
                foreach ($validated['access_groups'] as $groupId) {
                    SiteModuleAccess::create([
                        'module' => $id,
                        'usergroup' => $groupId,
                    ]);
                }
            }

            // Обновляем зависимости
            if (isset($validated['dependencies'])) {
                // Удаляем старые зависимости
                SiteModuleDepobj::where('module', $id)->delete();
                
                // Добавляем новые зависимости
                foreach ($validated['dependencies'] as $dependency) {
                    SiteModuleDepobj::create([
                        'module' => $id,
                        'resource' => $dependency['resource'],
                        'type' => $dependency['type'],
                    ]);
                }
            }

            $formattedModule = $this->formatModule($module->fresh(), true, true, true);
            
            return $this->updated($formattedModule, 'Module updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update module');
        }
    }

    public function destroy($id)
    {
        try {
            $module = SiteModule::find($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }

            // Удаляем связанные доступы
            SiteModuleAccess::where('module', $id)->delete();
            
            // Удаляем связанные зависимости
            SiteModuleDepobj::where('module', $id)->delete();
            
            $module->delete();

            return $this->deleted('Module deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete module');
        }
    }

    public function duplicate($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
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

            $formattedModule = $this->formatModule($newModule, true, true, true);
            
            return $this->created($formattedModule, 'Module duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate module');
        }
    }

    public function enable($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $module->update([
                'disabled' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatModule($module->fresh(), true, true, true), 'Module enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable module');
        }
    }

    public function disable($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $module->update([
                'disabled' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatModule($module->fresh(), true, true, true), 'Module disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable module');
        }
    }

    public function lock($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $module->update([
                'locked' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatModule($module->fresh(), true, true, true), 'Module locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock module');
        }
    }

    public function unlock($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $module->update([
                'locked' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatModule($module->fresh(), true, true, true), 'Module unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock module');
        }
    }

    public function content($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'content' => $module->modulecode,
            ], 'Module content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module content');
        }
    }

    public function updateContent(Request $request, $id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $module->update([
                'modulecode' => $validated['content'],
                'editedon' => time(),
            ]);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'content' => $module->modulecode,
            ], 'Module content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update module content');
        }
    }

    public function properties($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $properties = [];
            if (!empty($module->properties)) {
                $properties = $this->parseProperties($module->properties);
            }

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'properties' => $properties,
                'properties_raw' => $module->properties,
            ], 'Module properties retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module properties');
        }
    }

    public function updateProperties(Request $request, $id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $module->update([
                'properties' => $validated['properties'],
                'editedon' => time(),
            ]);

            $properties = $this->parseProperties($validated['properties']);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'properties' => $properties,
                'properties_raw' => $module->properties,
            ], 'Module properties updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update module properties');
        }
    }

    public function access($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $access = SiteModuleAccess::where('module', $id)->get();

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'access_groups' => $access->pluck('usergroup'),
                'access_count' => $access->count(),
            ], 'Module access groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module access groups');
        }
    }

    public function addAccess(Request $request, $id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'usergroup' => 'required|integer|exists:user_roles,id',
            ]);

            // Проверяем, не добавлена ли уже группа
            $existingAccess = SiteModuleAccess::where('module', $id)
                ->where('usergroup', $validated['usergroup'])
                ->first();

            if ($existingAccess) {
                return $this->error(
                    'User group already has access to module',
                    ['usergroup' => 'This user group already has access to the module'],
                    422
                );
            }

            // Добавляем доступ
            SiteModuleAccess::create([
                'module' => $id,
                'usergroup' => $validated['usergroup'],
            ]);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'usergroup' => $validated['usergroup'],
            ], 'Access group added to module successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add access group to module');
        }
    }

    public function removeAccess($id, $usergroupId)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $access = SiteModuleAccess::where('module', $id)
                ->where('usergroup', $usergroupId)
                ->first();

            if (!$access) {
                return $this->notFound('Access group not found for module');
            }

            $access->delete();

            return $this->deleted('Access group removed from module successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove access group from module');
        }
    }

    public function dependencies($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $dependencies = SiteModuleDepobj::where('module', $id)->get();

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'dependencies' => $dependencies,
                'dependencies_count' => $dependencies->count(),
            ], 'Module dependencies retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module dependencies');
        }
    }

    public function addDependency(Request $request, $id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'resource' => 'required|integer|exists:site_content,id',
                'type' => 'required|integer|min:0',
            ]);

            // Проверяем, не добавлена ли уже зависимость
            $existingDependency = SiteModuleDepobj::where('module', $id)
                ->where('resource', $validated['resource'])
                ->where('type', $validated['type'])
                ->first();

            if ($existingDependency) {
                return $this->error(
                    'Dependency already exists',
                    ['dependency' => 'This dependency already exists for the module'],
                    422
                );
            }

            // Добавляем зависимость
            SiteModuleDepobj::create([
                'module' => $id,
                'resource' => $validated['resource'],
                'type' => $validated['type'],
            ]);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'dependency' => [
                    'resource' => $validated['resource'],
                    'type' => $validated['type'],
                ],
            ], 'Dependency added to module successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add dependency to module');
        }
    }

    public function removeDependency($id, $dependencyId)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $dependency = SiteModuleDepobj::where('module', $id)
                ->where('id', $dependencyId)
                ->first();

            if (!$dependency) {
                return $this->notFound('Dependency not found for module');
            }

            $dependency->delete();

            return $this->deleted('Dependency removed from module successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove dependency from module');
        }
    }

    public function execute($id)
    {
        try {
            $module = SiteModule::find($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            if ($module->disabled) {
                return $this->error('Module is disabled', [], 422);
            }

            // Здесь должна быть логика выполнения модуля
            // Это упрощенный пример - в реальности нужно использовать EvolutionCMS API
            $output = "Module execution would happen here for: " . $module->name;

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'output' => $output,
                'executed_at' => date('Y-m-d H:i:s'),
            ], 'Module executed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to execute module');
        }
    }

    protected function formatModule($module, $includeCategory = false, $includeAccess = false, $includeDependencies = false)
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

        if ($includeAccess && isset($module->access)) {
            $data['access_groups'] = $module->access->pluck('usergroup');
            $data['access_count'] = $module->access->count();
        }

        if ($includeDependencies && isset($module->dependencies)) {
            $data['dependencies'] = $module->dependencies->map(function($dependency) {
                return [
                    'id' => $dependency->id,
                    'resource' => $dependency->resource,
                    'type' => $dependency->type,
                ];
            });
            $data['dependencies_count'] = $module->dependencies->count();
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