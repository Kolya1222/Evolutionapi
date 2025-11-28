<?php

namespace roilafx\Evolutionapi\Controllers\Content;

use roilafx\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use roilafx\Evolutionapi\Services\Content\CategoryService;

class CategoryController extends ApiController
{
    private $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

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

            // Поиск по названию категории - как в Evolution CMS
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where('category', 'LIKE', "%{$searchTerm}%");
            }

            // Сортировка как в Evolution CMS - по умолчанию rank ASC, category ASC
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
            
            return $this->paginated($formattedItems, $paginator, 'Categories retrieved successfully');
            
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

            $category = $this->categoryService->createCategory($validated);
            
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

            $category = $this->categoryService->updateCategory($id, $validated);

            return $this->updated($this->formatCategory($category), 'Category updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update category');
        }
    }

    public function destroy($id)
    {
        try {
            $this->categoryService->deleteCategory($id);

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
                        'created_at' => $this->categoryService->safeFormatDate($template->createdon),
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
                        'created_at' => $this->categoryService->safeFormatDate($chunk->createdon),
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
                        'created_at' => $this->categoryService->safeFormatDate($snippet->createdon),
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
                        'created_at' => $this->categoryService->safeFormatDate($plugin->createdon),
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
                        'created_at' => $this->categoryService->safeFormatDate($module->createdon),
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
            $validated = $this->validateRequest($request, [
                'target_category_id' => 'required|integer|exists:categories,id',
                'element_types' => 'required|array',
                'element_types.*' => 'string|in:templates,chunks,snippets,plugins,modules,tvs',
            ]);

            $result = $this->categoryService->moveElements(
                $id, 
                $validated['target_category_id'], 
                $validated['element_types']
            );

            return $this->success($result, 'Elements moved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move elements');
        }
    }

    public function stats()
    {
        try {
            $stats = $this->categoryService->getCategoriesStats();
            
            return $this->success($stats, 'Categories statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch categories statistics');
        }
    }

    public function uncategorizedElements($type)
    {
        try {
            $validTypes = ['templates', 'chunks', 'snippets', 'plugins', 'modules', 'tvs'];
            
            if (!in_array($type, $validTypes)) {
                return $this->error('Invalid element type', [
                    'available_types' => $validTypes
                ], 422);
            }

            $elements = $this->categoryService->getElementsNotInCategory($type);
            
            return $this->success($elements, 'Uncategorized ' . $type . ' retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch uncategorized elements');
        }
    }

    protected function formatCategory($category, $includeCounts = false, $includeElements = false)
    {
        $data = [
            'id' => $category->id,
            'name' => $category->category,
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
}