<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Content;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\Category;
use EvolutionCMS\Models\SiteTemplate;
use EvolutionCMS\Models\SiteHtmlsnippet;
use EvolutionCMS\Models\SiteSnippet;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SiteModule;
use EvolutionCMS\Models\SiteTmplvar;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends ApiController
{
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,category,rank',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_counts' => 'nullable|boolean',
                'include_elements' => 'nullable|boolean',
            ]);

            $query = Category::query();

            // Поиск по названию категории
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where('category', 'LIKE', "%{$searchTerm}%");
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'rank';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCounts = $request->get('include_counts', false);
            $includeElements = $request->get('include_elements', false);
            
            $formattedItems = collect($paginator->items())->map(function($category) use ($includeCounts, $includeElements) {
                return $this->formatCategory($category, $includeCounts, $includeElements);
            });
            
            return $this->success([
                'data' => $formattedItems,
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem()
                ]
            ], 'Categories retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch categories');
        }
    }

    public function show($id)
    {
        try {
            $category = Category::find($id);
                
            if (!$category) {
                return $this->notFound('Category not found');
            }
            
            $formattedCategory = $this->formatCategory($category, true, true);
            
            return $this->success($formattedCategory, 'Category retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch category');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'category' => 'required|string|max:45|unique:categories,category',
                'rank' => 'nullable|integer|min:0',
            ]);

            $categoryData = [
                'category' => $validated['category'],
                'rank' => $validated['rank'] ?? 0,
            ];

            $category = Category::create($categoryData);
            
            return $this->created($this->formatCategory($category), 'Category created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create category');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $category = Category::find($id);
                
            if (!$category) {
                return $this->notFound('Category not found');
            }

            $validated = $this->validateRequest($request, [
                'category' => 'sometimes|string|max:45|unique:categories,category,' . $id,
                'rank' => 'sometimes|integer|min:0',
            ]);

            $updateData = [];
            if (isset($validated['category'])) {
                $updateData['category'] = $validated['category'];
            }
            if (isset($validated['rank'])) {
                $updateData['rank'] = $validated['rank'];
            }

            $category->update($updateData);

            return $this->updated($this->formatCategory($category->fresh()), 'Category updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update category');
        }
    }

    public function destroy($id)
    {
        try {
            $category = Category::find($id);
                
            if (!$category) {
                return $this->notFound('Category not found');
            }

            // Проверяем, есть ли связанные элементы
            $hasElements = $this->categoryHasElements($category);
            if ($hasElements) {
                return $this->error(
                    'Cannot delete category with associated elements', 
                    ['category' => 'Category contains templates, chunks, snippets, plugins, modules or TVs'],
                    422
                );
            }

            $category->delete();

            return $this->deleted('Category deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete category');
        }
    }

    public function elements($id, $type = null)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return $this->notFound('Category not found');
            }

            $validTypes = ['templates', 'chunks', 'snippets', 'plugins', 'modules', 'tvs'];
            
            if ($type && !in_array($type, $validTypes)) {
                return $this->error('Invalid element type', [
                    'available_types' => $validTypes
                ], 422);
            }

            $elements = [];
            
            if (!$type || $type === 'templates') {
                $elements['templates'] = $category->templates->map(function($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->templatename,
                        'description' => $template->description,
                        'locked' => (bool)$template->locked,
                        'created_at' => $this->safeFormatDate($template->createdon),
                    ];
                });
            }

            if (!$type || $type === 'chunks') {
                $elements['chunks'] = $category->chunks->map(function($chunk) {
                    return [
                        'id' => $chunk->id,
                        'name' => $chunk->name,
                        'description' => $chunk->description,
                        'locked' => (bool)$chunk->locked,
                        'created_at' => $this->safeFormatDate($chunk->createdon),
                    ];
                });
            }

            if (!$type || $type === 'snippets') {
                $elements['snippets'] = $category->snippets->map(function($snippet) {
                    return [
                        'id' => $snippet->id,
                        'name' => $snippet->name,
                        'description' => $snippet->description,
                        'locked' => (bool)$snippet->locked,
                        'created_at' => $this->safeFormatDate($snippet->createdon),
                    ];
                });
            }

            if (!$type || $type === 'plugins') {
                $elements['plugins'] = $category->plugins->map(function($plugin) {
                    return [
                        'id' => $plugin->id,
                        'name' => $plugin->name,
                        'description' => $plugin->description,
                        'locked' => (bool)$plugin->locked,
                        'created_at' => $this->safeFormatDate($plugin->createdon),
                    ];
                });
            }

            if (!$type || $type === 'modules') {
                $elements['modules'] = $category->modules->map(function($module) {
                    return [
                        'id' => $module->id,
                        'name' => $module->name,
                        'description' => $module->description,
                        'locked' => (bool)$module->locked,
                        'created_at' => $this->safeFormatDate($module->createdon),
                    ];
                });
            }

            if (!$type || $type === 'tvs') {
                $elements['tvs'] = $category->tvs->map(function($tv) {
                    return [
                        'id' => $tv->id,
                        'name' => $tv->name,
                        'caption' => $tv->caption,
                        'type' => $tv->type,
                        'locked' => (bool)$tv->locked,
                    ];
                });
            }

            $message = $type 
                ? ucfirst($type) . ' in category retrieved successfully'
                : 'All elements in category retrieved successfully';

            return $this->success($elements, $message);

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch category elements');
        }
    }

    public function moveElements(Request $request, $id)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return $this->notFound('Category not found');
            }

            $validated = $this->validateRequest($request, [
                'target_category_id' => 'required|integer|exists:categories,id',
                'element_types' => 'required|array',
                'element_types.*' => 'string|in:templates,chunks,snippets,plugins,modules,tvs',
            ]);

            $targetCategory = Category::find($validated['target_category_id']);
            $movedCount = 0;
            $results = [];

            foreach ($validated['element_types'] as $type) {
                $count = 0;
                switch ($type) {
                    case 'templates':
                        $count = SiteTemplate::where('category', $id)->update(['category' => $targetCategory->id]);
                        break;
                    case 'chunks':
                        $count = SiteHtmlsnippet::where('category', $id)->update(['category' => $targetCategory->id]);
                        break;
                    case 'snippets':
                        $count = SiteSnippet::where('category', $id)->update(['category' => $targetCategory->id]);
                        break;
                    case 'plugins':
                        $count = SitePlugin::where('category', $id)->update(['category' => $targetCategory->id]);
                        break;
                    case 'modules':
                        $count = SiteModule::where('category', $id)->update(['category' => $targetCategory->id]);
                        break;
                    case 'tvs':
                        $count = SiteTmplvar::where('category', $id)->update(['category' => $targetCategory->id]);
                        break;
                }
                $results[$type] = $count;
                $movedCount += $count;
            }

            return $this->success([
                'moved_count' => $movedCount,
                'results' => $results,
                'source_category' => $this->formatCategory($category),
                'target_category' => $this->formatCategory($targetCategory),
            ], 'Elements moved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move elements');
        }
    }

    protected function formatCategory($category, $includeCounts = false, $includeElements = false)
    {
        $data = [
            'id' => $category->id,
            'name' => $category->category, // Используем алиас getNameAttribute
            'category' => $category->category,
            'rank' => $category->rank,
        ];

        if ($includeCounts) {
            $data['counts'] = [
                'templates' => $category->templates->count(),
                'chunks' => $category->chunks->count(),
                'snippets' => $category->snippets->count(),
                'plugins' => $category->plugins->count(),
                'modules' => $category->modules->count(),
                'tvs' => $category->tvs->count(),
                'total' => $category->templates->count() + $category->chunks->count() + 
                          $category->snippets->count() + $category->plugins->count() + 
                          $category->modules->count() + $category->tvs->count(),
            ];
        }

        if ($includeElements) {
            $data['elements'] = [
                'templates' => $category->templates->pluck('templatename', 'id'),
                'chunks' => $category->chunks->pluck('name', 'id'),
                'snippets' => $category->snippets->pluck('name', 'id'),
                'plugins' => $category->plugins->pluck('name', 'id'),
                'modules' => $category->modules->pluck('name', 'id'),
                'tvs' => $category->tvs->pluck('name', 'id'),
            ];
        }

        return $data;
    }

    protected function categoryHasElements($category)
    {
        return $category->templates->count() > 0 ||
               $category->chunks->count() > 0 ||
               $category->snippets->count() > 0 ||
               $category->plugins->count() > 0 ||
               $category->modules->count() > 0 ||
               $category->tvs->count() > 0;
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