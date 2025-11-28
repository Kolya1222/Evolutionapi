<?php

namespace roilafx\Evolutionapi\Controllers\Templates;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Templates\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TemplateController extends ApiController
{
    protected $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,templatename,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'selectable' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_tvs_count' => 'nullable|boolean',
            ]);

            $paginator = $this->templateService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeTvsCount = $request->get('include_tvs_count', false);
            
            $templates = collect($paginator->items())->map(function($template) use ($includeCategory, $includeTvsCount) {
                return $this->templateService->formatTemplate($template, $includeCategory, $includeTvsCount);
            });
            
            return $this->paginated($templates, $paginator, 'Templates retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch templates');
        }
    }

    public function show($id)
    {
        try {
            $template = $this->templateService->findById($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }
            
            $formattedTemplate = $this->templateService->formatTemplate($template, true, true);
            
            return $this->success($formattedTemplate, 'Template retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch template');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'templatename' => 'required|string|max:255|unique:site_templates,templatename',
                'description' => 'nullable|string',
                'content' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'template_type' => 'nullable|integer|min:0',
                'icon' => 'nullable|string|max:255',
                'locked' => 'nullable|boolean',
                'selectable' => 'nullable|boolean',
                'templatealias' => 'nullable|string|max:255',
                'templatecontroller' => 'nullable|string|max:255',
                'tv_ids' => 'nullable|array',
                'tv_ids.*' => 'integer|exists:site_tmplvars,id',
            ]);

            $template = $this->templateService->create($validated);
            $formattedTemplate = $this->templateService->formatTemplate($template, true, true);
            
            return $this->created($formattedTemplate, 'Template created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create template');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $template = $this->templateService->findById($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $validated = $this->validateRequest($request, [
                'templatename' => 'sometimes|string|max:255|unique:site_templates,templatename,' . $id,
                'description' => 'nullable|string',
                'content' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'template_type' => 'nullable|integer|min:0',
                'icon' => 'nullable|string|max:255',
                'locked' => 'nullable|boolean',
                'selectable' => 'nullable|boolean',
                'templatealias' => 'nullable|string|max:255',
                'templatecontroller' => 'nullable|string|max:255',
                'tv_ids' => 'nullable|array',
                'tv_ids.*' => 'integer|exists:site_tmplvars,id',
            ]);

            $updatedTemplate = $this->templateService->update($id, $validated);
            $formattedTemplate = $this->templateService->formatTemplate($updatedTemplate, true, true);
            
            return $this->updated($formattedTemplate, 'Template updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update template');
        }
    }

    public function destroy($id)
    {
        try {
            $template = $this->templateService->findById($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $this->templateService->delete($id);

            return $this->deleted('Template deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete template');
        }
    }

    public function tvs($id)
    {
        try {
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $tvs = $this->templateService->getTemplateTvs($id);

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'tvs' => $tvs,
                'tvs_count' => count($tvs),
            ], 'Template TVs retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch template TVs');
        }
    }

    public function addTv(Request $request, $id)
    {
        try {
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $validated = $this->validateRequest($request, [
                'tv_id' => 'required|integer|exists:site_tmplvars,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            $result = $this->templateService->addTvToTemplate(
                $id, 
                $validated['tv_id'], 
                $validated['rank'] ?? 0
            );

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'tv' => [
                    'id' => $result['tv']->id,
                    'name' => $result['tv']->name,
                    'caption' => $result['tv']->caption,
                    'type' => $result['tv']->type,
                    'rank' => $result['rank'],
                ],
            ], 'TV added to template successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add TV to template');
        }
    }

    public function removeTv($id, $tvId)
    {
        try {
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $this->templateService->removeTvFromTemplate($id, $tvId);

            return $this->deleted('TV removed from template successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove TV from template');
        }
    }

    public function duplicate($id)
    {
        try {
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $newTemplate = $this->templateService->duplicate($id);
            $formattedTemplate = $this->templateService->formatTemplate($newTemplate, true, true);
            
            return $this->created($formattedTemplate, 'Template duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate template');
        }
    }

    public function content($id)
    {
        try {
            $template = $this->templateService->getTemplateContent($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'content' => $template->content,
            ], 'Template content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch template content');
        }
    }
}