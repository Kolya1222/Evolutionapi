<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Elements;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteSnippet;
use EvolutionCMS\Models\SiteModule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SnippetController extends ApiController
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
                'cache_type' => 'nullable|boolean',
                'has_module' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_module' => 'nullable|boolean',
            ]);

            $query = SiteSnippet::query();

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

            // Фильтр по наличию модуля
            if ($request->has('has_module')) {
                if ($validated['has_module']) {
                    $query->whereNotNull('moduleguid')->where('moduleguid', '!=', '');
                } else {
                    $query->whereNull('moduleguid')->orWhere('moduleguid', '');
                }
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCategory = $request->get('include_category', false);
            $includeModule = $request->get('include_module', false);
            
            // Форматируем данные
            $snippets = collect($paginator->items())->map(function($snippet) use ($includeCategory, $includeModule) {
                return $this->formatSnippet($snippet, $includeCategory, $includeModule);
            });
            
            return $this->paginated($snippets, $paginator, 'Snippets retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippets');
        }
    }

    public function show($id)
    {
        try {
            $snippet = SiteSnippet::with(['categories', 'module'])->find($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }
            
            $formattedSnippet = $this->formatSnippet($snippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippet');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:site_snippets,name',
                'description' => 'nullable|string',
                'snippet' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'module_guid' => 'nullable|string|max:255',
            ]);

            $snippetData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'snippet' => $validated['snippet'],
                'category' => $validated['category'] ?? 0,
                'editor_type' => $validated['editor_type'] ?? 0,
                'cache_type' => $validated['cache_type'] ?? false,
                'locked' => $validated['locked'] ?? false,
                'disabled' => $validated['disabled'] ?? false,
                'properties' => $validated['properties'] ?? '',
                'moduleguid' => $validated['module_guid'] ?? '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $snippet = SiteSnippet::create($snippetData);

            $formattedSnippet = $this->formatSnippet($snippet, true, true);
            
            return $this->created($formattedSnippet, 'Snippet created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create snippet');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $snippet = SiteSnippet::find($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_snippets,name,' . $id,
                'description' => 'nullable|string',
                'snippet' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'module_guid' => 'nullable|string|max:255',
            ]);

            $updateData = [];
            $fields = [
                'name', 'description', 'snippet', 'category', 'editor_type',
                'cache_type', 'locked', 'disabled', 'properties', 'moduleguid'
            ];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    if ($field === 'module_guid') {
                        $updateData['moduleguid'] = $validated[$field];
                    } else {
                        $updateData[$field] = $validated[$field];
                    }
                }
            }

            $updateData['editedon'] = time();

            $snippet->update($updateData);

            $formattedSnippet = $this->formatSnippet($snippet->fresh(), true, true);
            
            return $this->updated($formattedSnippet, 'Snippet updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update snippet');
        }
    }

    public function destroy($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $snippet->delete();

            return $this->deleted('Snippet deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete snippet');
        }
    }

    public function duplicate($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            // Создаем копию сниппета
            $newSnippet = $snippet->replicate();
            $newSnippet->name = $snippet->name . ' (Copy)';
            $newSnippet->createdon = time();
            $newSnippet->editedon = time();
            $newSnippet->save();

            $formattedSnippet = $this->formatSnippet($newSnippet, true, true);
            
            return $this->created($formattedSnippet, 'Snippet duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate snippet');
        }
    }

    public function enable($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $snippet->update([
                'disabled' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatSnippet($snippet->fresh(), true, true), 'Snippet enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable snippet');
        }
    }

    public function disable($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $snippet->update([
                'disabled' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatSnippet($snippet->fresh(), true, true), 'Snippet disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable snippet');
        }
    }

    public function lock($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $snippet->update([
                'locked' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatSnippet($snippet->fresh(), true, true), 'Snippet locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock snippet');
        }
    }

    public function unlock($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $snippet->update([
                'locked' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatSnippet($snippet->fresh(), true, true), 'Snippet unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock snippet');
        }
    }

    public function content($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'content' => $snippet->snippet,
                'source_code' => $snippet->sourceCode,
            ], 'Snippet content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippet content');
        }
    }

    public function updateContent(Request $request, $id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $snippet->update([
                'snippet' => $validated['content'],
                'editedon' => time(),
            ]);

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'content' => $snippet->snippet,
                'source_code' => $snippet->sourceCode,
            ], 'Snippet content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update snippet content');
        }
    }

    public function properties($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $properties = [];
            if (!empty($snippet->properties)) {
                $properties = $this->parseProperties($snippet->properties);
            }

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'properties' => $properties,
                'properties_raw' => $snippet->properties,
            ], 'Snippet properties retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch snippet properties');
        }
    }

    public function updateProperties(Request $request, $id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $snippet->update([
                'properties' => $validated['properties'],
                'editedon' => time(),
            ]);

            $properties = $this->parseProperties($validated['properties']);

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'properties' => $properties,
                'properties_raw' => $snippet->properties,
            ], 'Snippet properties updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update snippet properties');
        }
    }

    public function execute($id, Request $request)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            if ($snippet->disabled) {
                return $this->error('Snippet is disabled', [], 423);
            }

            // Получаем параметры для сниппета
            $params = $request->all();

            // Выполняем сниппет через EvolutionCMS
            $output = evolutionCMS()->evalSnippet($snippet->snippet, $params);

            return $this->success([
                'snippet_id' => $snippet->id,
                'snippet_name' => $snippet->name,
                'output' => $output,
                'executed_at' => now()->toISOString(),
            ], 'Snippet executed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to execute snippet');
        }
    }

    public function attachModule(Request $request, $id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'module_guid' => 'required|string|max:255|exists:site_modules,guid',
            ]);

            $module = SiteModule::where('guid', $validated['module_guid'])->first();
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $snippet->update([
                'moduleguid' => $validated['module_guid'],
                'editedon' => time(),
            ]);

            return $this->success($this->formatSnippet($snippet->fresh(), true, true), 'Module attached to snippet successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to attach module to snippet');
        }
    }

    public function detachModule($id)
    {
        try {
            $snippet = SiteSnippet::find($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $snippet->update([
                'moduleguid' => '',
                'editedon' => time(),
            ]);

            return $this->success($this->formatSnippet($snippet->fresh(), true, true), 'Module detached from snippet successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to detach module from snippet');
        }
    }

    protected function formatSnippet($snippet, $includeCategory = false, $includeModule = false)
    {
        $data = [
            'id' => $snippet->id,
            'name' => $snippet->name,
            'description' => $snippet->description,
            'editor_type' => $snippet->editor_type,
            'cache_type' => (bool)$snippet->cache_type,
            'locked' => (bool)$snippet->locked,
            'disabled' => (bool)$snippet->disabled,
            'guid' => $snippet->guid,
            'has_module' => $snippet->hasModule,
            'created_at' => $this->safeFormatDate($snippet->createdon),
            'updated_at' => $this->safeFormatDate($snippet->editedon),
            'is_locked' => $snippet->isAlreadyEdit,
            'locked_info' => $snippet->alreadyEditInfo,
        ];

        if ($includeCategory && $snippet->categories) {
            $data['category'] = [
                'id' => $snippet->categories->id,
                'name' => $snippet->categories->category,
            ];
        }

        if ($includeModule && $snippet->module) {
            $data['module'] = [
                'id' => $snippet->module->id,
                'name' => $snippet->module->name,
                'guid' => $snippet->module->guid,
                'disabled' => (bool)$snippet->module->disabled,
            ];
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