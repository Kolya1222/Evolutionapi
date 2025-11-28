<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ModuleController extends ApiController
{
    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

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

            $paginator = $this->moduleService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeAccess = $request->get('include_access', false);
            $includeDependencies = $request->get('include_dependencies', false);
            
            $modules = collect($paginator->items())->map(function($module) use ($includeCategory, $includeAccess, $includeDependencies) {
                return $this->moduleService->formatModule($module, $includeCategory, $includeAccess, $includeDependencies);
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
            $module = $this->moduleService->findById($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }
            
            $formattedModule = $this->moduleService->formatModule($module, true, true, true);
            
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

            $module = $this->moduleService->create($validated);
            $formattedModule = $this->moduleService->formatModule($module, true, true, true);
            
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
            $module = $this->moduleService->findById($id);
                
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

            $updatedModule = $this->moduleService->update($id, $validated);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
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
            $module = $this->moduleService->findById($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $this->moduleService->delete($id);

            return $this->deleted('Module deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete module');
        }
    }

    public function duplicate($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $newModule = $this->moduleService->duplicate($id);
            $formattedModule = $this->moduleService->formatModule($newModule, true, true, true);
            
            return $this->created($formattedModule, 'Module duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate module');
        }
    }

    public function enable($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'disabled', false);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable module');
        }
    }

    public function disable($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'disabled', true);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable module');
        }
    }

    public function lock($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'locked', true);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock module');
        }
    }

    public function unlock($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'locked', false);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock module');
        }
    }

    public function content($id)
    {
        try {
            $module = $this->moduleService->findById($id);
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $updatedModule = $this->moduleService->updateContent($id, $validated['content']);

            return $this->success([
                'module_id' => $updatedModule->id,
                'module_name' => $updatedModule->name,
                'content' => $updatedModule->modulecode,
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $properties = $this->moduleService->parseProperties($module->properties);

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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $updatedModule = $this->moduleService->updateProperties($id, $validated['properties']);
            $properties = $this->moduleService->parseProperties($validated['properties']);

            return $this->success([
                'module_id' => $updatedModule->id,
                'module_name' => $updatedModule->name,
                'properties' => $properties,
                'properties_raw' => $updatedModule->properties,
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $accessGroups = $this->moduleService->getModuleAccess($id);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'access_groups' => $accessGroups,
                'access_count' => count($accessGroups),
            ], 'Module access groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module access groups');
        }
    }

    public function addAccess(Request $request, $id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'usergroup' => 'required|integer|exists:user_roles,id',
            ]);

            $result = $this->moduleService->addAccess($id, $validated['usergroup']);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'usergroup' => $result['usergroup'],
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $this->moduleService->removeAccess($id, $usergroupId);

            return $this->deleted('Access group removed from module successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove access group from module');
        }
    }

    public function dependencies($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $dependencies = $this->moduleService->getModuleDependencies($id);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'dependencies' => $dependencies,
                'dependencies_count' => count($dependencies),
            ], 'Module dependencies retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module dependencies');
        }
    }

    public function addDependency(Request $request, $id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'resource' => 'required|integer|exists:site_content,id',
                'type' => 'required|integer|min:0',
            ]);

            $result = $this->moduleService->addDependency($id, $validated['resource'], $validated['type']);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'dependency' => $result,
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $this->moduleService->removeDependency($id, $dependencyId);

            return $this->deleted('Dependency removed from module successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove dependency from module');
        }
    }

    public function execute($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            if ($module->disabled) {
                return $this->error('Module is disabled', [], 422);
            }
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
}