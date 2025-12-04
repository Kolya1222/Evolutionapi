<?php

namespace roilafx\Evolutionapi\Services\Content;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\Category;
use EvolutionCMS\Models\SiteTemplate;
use EvolutionCMS\Models\SiteHtmlsnippet;
use EvolutionCMS\Models\SiteSnippet;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SiteModule;
use EvolutionCMS\Models\SiteTmplvar;
use Exception;
use Illuminate\Support\Collection;

class CategoryService extends BaseService
{
    /**
     * Создание категории - используем логику из Legacy/Categories.php
     */
    public function createCategory(array $data): Category
    {
        $categoryData = [
            'category' => $data['category'],
            'rank' => $data['rank'] ?? 0,
        ];

        $category = Category::create($categoryData);

        // Логируем действие как в Evolution CMS
        $this->logManagerAction('category_new', $category->id, $category->category);

        return $category;
    }

    /**
     * Обновление категории - используем логику из Legacy/Categories.php
     */
    public function updateCategory(int $id, array $data): Category
    {
        $category = Category::find($id);
        if (!$category) {
            throw new Exception('Category not found');
        }

        // Проверка уникальности при изменении имени
        if (isset($data['category']) && $data['category'] !== $category->category) {
            $existing = Category::where('category', $data['category'])
                ->where('id', '!=', $id)
                ->first();
                
            if ($existing) {
                throw new Exception('Category with this name already exists');
            }
        }

        $updateData = [];
        if (isset($data['category'])) {
            $updateData['category'] = $data['category'];
        }
        if (isset($data['rank'])) {
            $updateData['rank'] = $data['rank'];
        }

        $category->update($updateData);

        $this->logManagerAction('category_save', $category->id, $category->category);

        return $category->fresh();
    }

    /**
     * Удаление категории - используем логику из Legacy/Categories.php строка 141
     */
    public function deleteCategory(int $id): bool
    {
        $category = Category::find($id);
        if (!$category) {
            throw new Exception('Category not found');
        }

        // Проверяем, есть ли связанные элементы
        if ($this->categoryHasElements($category)) {
            throw new Exception('Cannot delete category with associated elements');
        }

        // Вызываем событие перед удалением
        $this->invokeEvent('OnBeforeCategoryDelete', [
            'id' => $category->id,
            'category' => $category
        ]);

        $category->delete();

        // Вызываем событие после удаления
        $this->invokeEvent('OnCategoryDelete', [
            'id' => $category->id,
            'category' => $category
        ]);

        $this->logManagerAction('category_delete', $category->id, $category->category);

        return true;
    }

    /**
     * Перемещение элементов между категориями - улучшенная версия
     */
    public function moveElements(int $sourceCategoryId, int $targetCategoryId, array $elementTypes): array
    {
        $sourceCategory = Category::find($sourceCategoryId);
        $targetCategory = Category::find($targetCategoryId);
        
        if (!$sourceCategory || !$targetCategory) {
            throw new Exception('Source or target category not found');
        }

        $movedCount = 0;
        $results = [];

        foreach ($elementTypes as $type) {
            $count = 0;
            
            // Вызываем событие перед перемещением
            $this->invokeEvent('OnBeforeCategoryElementMove', [
                'source_category' => $sourceCategory,
                'target_category' => $targetCategory,
                'element_type' => $type
            ]);

            // Используем ту же логику что и в Legacy/Categories.php
            switch ($type) {
                case 'templates':
                    $count = SiteTemplate::where('category', $sourceCategoryId)
                        ->update(['category' => $targetCategoryId]);
                    break;
                    
                case 'chunks':
                    $count = SiteHtmlsnippet::where('category', $sourceCategoryId)
                        ->update(['category' => $targetCategoryId]);
                    break;
                    
                case 'snippets':
                    $count = SiteSnippet::where('category', $sourceCategoryId)
                        ->update(['category' => $targetCategoryId]);
                    break;
                    
                case 'plugins':
                    $count = SitePlugin::where('category', $sourceCategoryId)
                        ->update(['category' => $targetCategoryId]);
                    break;
                    
                case 'modules':
                    $count = SiteModule::where('category', $sourceCategoryId)
                        ->update(['category' => $targetCategoryId]);
                    break;
                    
                case 'tvs':
                    $count = SiteTmplvar::where('category', $sourceCategoryId)
                        ->update(['category' => $targetCategoryId]);
                    break;
            }

            // Вызываем событие после перемещения
            $this->invokeEvent('OnAfterCategoryElementMove', [
                'source_category' => $sourceCategory,
                'target_category' => $targetCategory,
                'element_type' => $type,
                'moved_count' => $count
            ]);

            $results[$type] = $count;
            $movedCount += $count;
        }

        $this->logManagerAction('category_move_elements', $sourceCategoryId, 
            "Moved {$movedCount} elements to category {$targetCategory->category}");

        return [
            'moved_count' => $movedCount,
            'results' => $results,
            'source_category' => [
                'id' => $sourceCategory->id,
                'name' => $sourceCategory->category
            ],
            'target_category' => [
                'id' => $targetCategory->id,
                'name' => $targetCategory->category
            ],
        ];
    }

    /**
     * Получение статистики по категориям - используем withCount как в модели
     */
    public function getCategoriesStats(): Collection
    {
        return Category::withCount([
            'templates', 
            'chunks', 
            'snippets', 
            'plugins', 
            'modules', 
            'tvs'
        ])
        ->orderBy('rank', 'ASC')
        ->orderBy('category', 'ASC')
        ->get()->map(function($category) {
            return [
                'id' => $category->id,
                'name' => $category->category,
                'rank' => $category->rank,
                'counts' => [
                    'templates' => $category->templates_count,
                    'chunks' => $category->chunks_count,
                    'snippets' => $category->snippets_count,
                    'plugins' => $category->plugins_count,
                    'modules' => $category->modules_count,
                    'tvs' => $category->tvs_count,
                    'total' => $category->templates_count + $category->chunks_count + 
                              $category->snippets_count + $category->plugins_count + 
                              $category->modules_count + $category->tvs_count,
                ]
            ];
        });
    }

    /**
     * Получение всех категорий с элементами - как в Resources контроллерах
     */
    public function getCategoriesWithElements(): Collection
    {
        return Category::with([
            'templates' => function($query) {
                $query->orderBy('templatename', 'ASC');
            },
            'chunks' => function($query) {
                $query->orderBy('name', 'ASC');
            },
            'snippets' => function($query) {
                $query->orderBy('name', 'ASC');
            },
            'plugins' => function($query) {
                $query->orderBy('name', 'ASC');
            },
            'modules' => function($query) {
                $query->orderBy('name', 'ASC');
            },
            'tvs' => function($query) {
                $query->orderBy('name', 'ASC');
            }
        ])
        ->orderBy('rank', 'ASC')
        ->orderBy('category', 'ASC')
        ->get();
    }

    /**
     * Проверка наличия элементов в категории - как в Legacy/Categories.php
     */
    private function categoryHasElements(Category $category): bool
    {
        return $category->templates->count() > 0 ||
               $category->chunks->count() > 0 ||
               $category->snippets->count() > 0 ||
               $category->plugins->count() > 0 ||
               $category->modules->count() > 0 ||
               $category->tvs->count() > 0;
    }

    /**
     * Получение элементов не в категории - как в Resources контроллерах
     */
    public function getElementsNotInCategory(string $elementType): Collection
    {
        switch ($elementType) {
            case 'templates':
                return SiteTemplate::where('category', 0)
                    ->orderBy('templatename', 'ASC')
                    ->get();
                    
            case 'chunks':
                return SiteHtmlsnippet::where('category', 0)
                    ->orderBy('name', 'ASC')
                    ->get();
                    
            case 'snippets':
                return SiteSnippet::where('category', 0)
                    ->orderBy('name', 'ASC')
                    ->get();
                    
            case 'plugins':
                return SitePlugin::where('category', 0)
                    ->orderBy('name', 'ASC')
                    ->get();
                    
            case 'modules':
                return SiteModule::where('category', 0)
                    ->orderBy('name', 'ASC')
                    ->get();
                    
            case 'tvs':
                return SiteTmplvar::where('category', 0)
                    ->orderBy('name', 'ASC')
                    ->get();
                    
            default:
                throw new Exception('Invalid element type');
        }
    }
}