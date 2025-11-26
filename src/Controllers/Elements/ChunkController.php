<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Elements;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteHtmlsnippet;
use EvolutionCMS\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChunkController extends ApiController
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
                'include_category' => 'nullable|boolean',
            ]);

            $query = SiteHtmlsnippet::query();

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

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCategory = $request->get('include_category', false);
            
            // Форматируем данные
            $chunks = collect($paginator->items())->map(function($chunk) use ($includeCategory) {
                return $this->formatChunk($chunk, $includeCategory);
            });
            
            return $this->paginated($chunks, $paginator, 'Chunks retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch chunks');
        }
    }

    public function show($id)
    {
        try {
            $chunk = SiteHtmlsnippet::with('categories')->find($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }
            
            $formattedChunk = $this->formatChunk($chunk, true);
            
            return $this->success($formattedChunk, 'Chunk retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch chunk');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:site_htmlsnippets,name',
                'description' => 'nullable|string',
                'snippet' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'editor_name' => 'nullable|string|max:255',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
            ]);

            $chunkData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'snippet' => $validated['snippet'],
                'category' => $validated['category'] ?? 0,
                'editor_type' => $validated['editor_type'] ?? 0,
                'editor_name' => $validated['editor_name'] ?? '',
                'cache_type' => $validated['cache_type'] ?? false,
                'locked' => $validated['locked'] ?? false,
                'disabled' => $validated['disabled'] ?? false,
                'createdon' => time(),
                'editedon' => time(),
            ];

            $chunk = SiteHtmlsnippet::create($chunkData);

            $formattedChunk = $this->formatChunk($chunk, true);
            
            return $this->created($formattedChunk, 'Chunk created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create chunk');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_htmlsnippets,name,' . $id,
                'description' => 'nullable|string',
                'snippet' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'editor_name' => 'nullable|string|max:255',
                'cache_type' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
            ]);

            $updateData = [];
            $fields = [
                'name', 'description', 'snippet', 'category', 'editor_type',
                'editor_name', 'cache_type', 'locked', 'disabled'
            ];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $updateData['editedon'] = time();

            $chunk->update($updateData);

            $formattedChunk = $this->formatChunk($chunk->fresh(), true);
            
            return $this->updated($formattedChunk, 'Chunk updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update chunk');
        }
    }

    public function destroy($id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $chunk->delete();

            return $this->deleted('Chunk deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete chunk');
        }
    }

    public function duplicate($id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            // Создаем копию чанка
            $newChunk = $chunk->replicate();
            $newChunk->name = $chunk->name . ' (Copy)';
            $newChunk->createdon = time();
            $newChunk->editedon = time();
            $newChunk->save();

            $formattedChunk = $this->formatChunk($newChunk, true);
            
            return $this->created($formattedChunk, 'Chunk duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate chunk');
        }
    }

    public function enable($id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $chunk->update([
                'disabled' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatChunk($chunk->fresh(), true), 'Chunk enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable chunk');
        }
    }

    public function disable($id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $chunk->update([
                'disabled' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatChunk($chunk->fresh(), true), 'Chunk disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable chunk');
        }
    }

    public function lock($id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $chunk->update([
                'locked' => true,
                'editedon' => time(),
            ]);

            return $this->success($this->formatChunk($chunk->fresh(), true), 'Chunk locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock chunk');
        }
    }

    public function unlock($id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $chunk->update([
                'locked' => false,
                'editedon' => time(),
            ]);

            return $this->success($this->formatChunk($chunk->fresh(), true), 'Chunk unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock chunk');
        }
    }

    public function content($id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            return $this->success([
                'chunk_id' => $chunk->id,
                'chunk_name' => $chunk->name,
                'content' => $chunk->snippet,
            ], 'Chunk content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch chunk content');
        }
    }

    public function updateContent(Request $request, $id)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $chunk->update([
                'snippet' => $validated['content'],
                'editedon' => time(),
            ]);

            return $this->success([
                'chunk_id' => $chunk->id,
                'chunk_name' => $chunk->name,
                'content' => $chunk->snippet,
            ], 'Chunk content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update chunk content');
        }
    }

    public function execute($id, Request $request)
    {
        try {
            $chunk = SiteHtmlsnippet::find($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            if ($chunk->disabled) {
                return $this->error('Chunk is disabled', [], 423);
            }

            // Получаем параметры для чанка
            $params = $request->all();

            // Выполняем чанк через EvolutionCMS
            $output = evolutionCMS()->evalSnippet($chunk->snippet, $params);

            return $this->success([
                'chunk_id' => $chunk->id,
                'chunk_name' => $chunk->name,
                'output' => $output,
                'executed_at' => now()->toISOString(),
            ], 'Chunk executed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to execute chunk');
        }
    }

    protected function formatChunk($chunk, $includeCategory = false)
    {
        $data = [
            'id' => $chunk->id,
            'name' => $chunk->name,
            'description' => $chunk->description,
            'editor_type' => $chunk->editor_type,
            'editor_name' => $chunk->editor_name,
            'cache_type' => (bool)$chunk->cache_type,
            'locked' => (bool)$chunk->locked,
            'disabled' => (bool)$chunk->disabled,
            'created_at' => $this->safeFormatDate($chunk->createdon),
            'updated_at' => $this->safeFormatDate($chunk->editedon),
            'is_locked' => $chunk->isAlreadyEdit,
            'locked_info' => $chunk->alreadyEditInfo,
        ];

        if ($includeCategory && $chunk->categories) {
            $data['category'] = [
                'id' => $chunk->categories->id,
                'name' => $chunk->categories->category,
            ];
        }

        return $data;
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