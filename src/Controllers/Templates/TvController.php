<?php

namespace roilafx\Evolutionapi\Controllers\Templates;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Templates\TvService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TvController extends ApiController
{
    protected $tvService;

    public function __construct(TvService $tvService)
    {
        $this->tvService = $tvService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name,caption,rank,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'type' => 'nullable|string|max:255',
                'include_category' => 'nullable|boolean',
                'include_templates_count' => 'nullable|boolean',
                'include_access_count' => 'nullable|boolean',
            ]);

            $paginator = $this->tvService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeTemplatesCount = $request->get('include_templates_count', false);
            $includeAccessCount = $request->get('include_access_count', false);
            
            $tvs = collect($paginator->items())->map(function($tv) use ($includeCategory, $includeTemplatesCount, $includeAccessCount) {
                return $this->tvService->formatTv($tv, $includeCategory, $includeTemplatesCount, $includeAccessCount);
            });
            
            return $this->paginated($tvs, $paginator, 'TVs retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TVs');
        }
    }

    public function show($id)
    {
        try {
            $tv = $this->tvService->findById($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }
            
            $formattedTv = $this->tvService->formatTv($tv, true, true, true);
            
            return $this->success($formattedTv, 'TV retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:site_tmplvars,name',
                'caption' => 'required|string|max:255',
                'type' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'locked' => 'nullable|boolean',
                'elements' => 'nullable|string',
                'rank' => 'nullable|integer|min:0',
                'display' => 'nullable|string|max:255',
                'display_params' => 'nullable|string',
                'default_text' => 'nullable|string',
                'properties' => 'nullable|array',
                'template_ids' => 'nullable|array',
                'template_ids.*' => 'integer|exists:site_templates,id',
                'document_group_ids' => 'nullable|array',
                'document_group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $tv = $this->tvService->create($validated);
            $formattedTv = $this->tvService->formatTv($tv, true, true, true);
            
            return $this->created($formattedTv, 'TV created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create TV');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tv = $this->tvService->findById($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_tmplvars,name,' . $id,
                'caption' => 'sometimes|string|max:255',
                'type' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'locked' => 'nullable|boolean',
                'elements' => 'nullable|string',
                'rank' => 'nullable|integer|min:0',
                'display' => 'nullable|string|max:255',
                'display_params' => 'nullable|string',
                'default_text' => 'nullable|string',
                'properties' => 'nullable|array',
                'template_ids' => 'nullable|array',
                'template_ids.*' => 'integer|exists:site_templates,id',
                'document_group_ids' => 'nullable|array',
                'document_group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $updatedTv = $this->tvService->update($id, $validated);
            $formattedTv = $this->tvService->formatTv($updatedTv, true, true, true);
            
            return $this->updated($formattedTv, 'TV updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update TV');
        }
    }

    public function destroy($id)
    {
        try {
            $tv = $this->tvService->findById($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $this->tvService->delete($id);

            return $this->deleted('TV deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV');
        }
    }

    public function templates($id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $templates = $this->tvService->getTvTemplates($id);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'templates' => $templates,
                'templates_count' => count($templates),
            ], 'TV templates retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV templates');
        }
    }

    public function addTemplate(Request $request, $id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'template_id' => 'required|integer|exists:site_templates,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            $result = $this->tvService->addTemplateToTv(
                $id, 
                $validated['template_id'], 
                $validated['rank'] ?? 0
            );

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'template' => [
                    'id' => $result['template']->id,
                    'name' => $result['template']->templatename,
                    'description' => $result['template']->description,
                    'rank' => $result['rank'],
                ],
            ], 'Template added to TV successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add template to TV');
        }
    }

    public function removeTemplate($id, $templateId)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $this->tvService->removeTemplateFromTv($id, $templateId);

            return $this->deleted('Template removed from TV successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove template from TV');
        }
    }

    public function access($id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $access = $this->tvService->getTvAccess($id);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'access' => $access,
                'access_count' => count($access),
            ], 'TV access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV access');
        }
    }

    public function addAccess(Request $request, $id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'document_group_id' => 'required|integer|exists:documentgroup_names,id',
            ]);

            $result = $this->tvService->addAccessToTv($id, $validated['document_group_id']);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'access' => [
                    'id' => $result['access']->id,
                    'document_group_id' => $result['document_group']->id,
                    'document_group_name' => $result['document_group']->name,
                ],
            ], 'Access added to TV successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add access to TV');
        }
    }

    public function removeAccess($id, $accessId)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $this->tvService->removeAccessFromTv($id, $accessId);

            return $this->deleted('Access removed from TV successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove access from TV');
        }
    }

    public function duplicate($id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $newTv = $this->tvService->duplicate($id);
            $formattedTv = $this->tvService->formatTv($newTv, true, true, true);
            
            return $this->created($formattedTv, 'TV duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate TV');
        }
    }
}