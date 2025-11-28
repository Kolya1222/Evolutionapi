<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\ChunkService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChunkController extends ApiController
{
    protected $chunkService;

    public function __construct(ChunkService $chunkService)
    {
        $this->chunkService = $chunkService;
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
                'cache_type' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
            ]);

            $paginator = $this->chunkService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            
            $chunks = collect($paginator->items())->map(function($chunk) use ($includeCategory) {
                return $this->chunkService->formatChunk($chunk, $includeCategory);
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
            $chunk = $this->chunkService->findById($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }
            
            $formattedChunk = $this->chunkService->formatChunk($chunk, true);
            
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

            $chunk = $this->chunkService->create($validated);
            $formattedChunk = $this->chunkService->formatChunk($chunk, true);
            
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
            $chunk = $this->chunkService->findById($id);
                
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

            $updatedChunk = $this->chunkService->update($id, $validated);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
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
            $chunk = $this->chunkService->findById($id);
                
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $this->chunkService->delete($id);

            return $this->deleted('Chunk deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete chunk');
        }
    }

    public function duplicate($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $newChunk = $this->chunkService->duplicate($id);
            $formattedChunk = $this->chunkService->formatChunk($newChunk, true);
            
            return $this->created($formattedChunk, 'Chunk duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate chunk');
        }
    }

    public function enable($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'disabled', false);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable chunk');
        }
    }

    public function disable($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'disabled', true);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable chunk');
        }
    }

    public function lock($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'locked', true);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock chunk');
        }
    }

    public function unlock($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $updatedChunk = $this->chunkService->toggleStatus($id, 'locked', false);
            $formattedChunk = $this->chunkService->formatChunk($updatedChunk, true);
            
            return $this->success($formattedChunk, 'Chunk unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock chunk');
        }
    }

    public function content($id)
    {
        try {
            $chunk = $this->chunkService->findById($id);
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
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $updatedChunk = $this->chunkService->updateContent($id, $validated['content']);

            return $this->success([
                'chunk_id' => $updatedChunk->id,
                'chunk_name' => $updatedChunk->name,
                'content' => $updatedChunk->snippet,
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
            $chunk = $this->chunkService->findById($id);
            if (!$chunk) {
                return $this->notFound('Chunk not found');
            }

            if ($chunk->disabled) {
                return $this->error('Chunk is disabled', [], 423);
            }

            $params = $request->all();
            $output = $this->chunkService->execute($id, $params);

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
}