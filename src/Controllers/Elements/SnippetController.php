<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\SnippetService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SnippetController extends ApiController
{
    protected $snippetService;

    public function __construct(SnippetService $snippetService)
    {
        $this->snippetService = $snippetService;
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
                'has_module' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_module' => 'nullable|boolean',
            ]);

            $paginator = $this->snippetService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeModule = $request->get('include_module', false);
            
            $snippets = collect($paginator->items())->map(function($snippet) use ($includeCategory, $includeModule) {
                return $this->snippetService->formatSnippet($snippet, $includeCategory, $includeModule);
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
            $snippet = $this->snippetService->findById($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }
            
            $formattedSnippet = $this->snippetService->formatSnippet($snippet, true, true);
            
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

            $snippet = $this->snippetService->create($validated);
            $formattedSnippet = $this->snippetService->formatSnippet($snippet, true, true);
            
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
            $snippet = $this->snippetService->findById($id);
                
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

            $updatedSnippet = $this->snippetService->update($id, $validated);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
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
            $snippet = $this->snippetService->findById($id);
                
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $this->snippetService->delete($id);

            return $this->deleted('Snippet deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete snippet');
        }
    }

    public function duplicate($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $newSnippet = $this->snippetService->duplicate($id);
            $formattedSnippet = $this->snippetService->formatSnippet($newSnippet, true, true);
            
            return $this->created($formattedSnippet, 'Snippet duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate snippet');
        }
    }

    public function enable($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'disabled', false);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable snippet');
        }
    }

    public function disable($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'disabled', true);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable snippet');
        }
    }

    public function lock($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'locked', true);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock snippet');
        }
    }

    public function unlock($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->toggleStatus($id, 'locked', false);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Snippet unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock snippet');
        }
    }

    public function content($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $updatedSnippet = $this->snippetService->updateContent($id, $validated['content']);

            return $this->success([
                'snippet_id' => $updatedSnippet->id,
                'snippet_name' => $updatedSnippet->name,
                'content' => $updatedSnippet->snippet,
                'source_code' => $updatedSnippet->sourceCode,
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $properties = $this->snippetService->parseProperties($snippet->properties);

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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $updatedSnippet = $this->snippetService->updateProperties($id, $validated['properties']);
            $properties = $this->snippetService->parseProperties($validated['properties']);

            return $this->success([
                'snippet_id' => $updatedSnippet->id,
                'snippet_name' => $updatedSnippet->name,
                'properties' => $properties,
                'properties_raw' => $updatedSnippet->properties,
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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            if ($snippet->disabled) {
                return $this->error('Snippet is disabled', [], 423);
            }

            $params = $request->all();
            $output = $this->snippetService->execute($id, $params);

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
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $validated = $this->validateRequest($request, [
                'module_guid' => 'required|string|max:255|exists:site_modules,guid',
            ]);

            $updatedSnippet = $this->snippetService->attachModule($id, $validated['module_guid']);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Module attached to snippet successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to attach module to snippet');
        }
    }

    public function detachModule($id)
    {
        try {
            $snippet = $this->snippetService->findById($id);
            if (!$snippet) {
                return $this->notFound('Snippet not found');
            }

            $updatedSnippet = $this->snippetService->detachModule($id);
            $formattedSnippet = $this->snippetService->formatSnippet($updatedSnippet, true, true);
            
            return $this->success($formattedSnippet, 'Module detached from snippet successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to detach module from snippet');
        }
    }
}