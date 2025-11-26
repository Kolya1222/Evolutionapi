<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Templates;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteTemplate;
use EvolutionCMS\Models\Category;
use EvolutionCMS\Models\SiteTmplvar;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TemplateController extends ApiController
{
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

            $query = SiteTemplate::query();

            // Поиск по названию или описанию
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('templatename', 'LIKE', "%{$searchTerm}%")
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

            // Фильтр по selectable
            if ($request->has('selectable')) {
                $query->where('selectable', $validated['selectable']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'templatename';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCategory = $request->get('include_category', false);
            $includeTvsCount = $request->get('include_tvs_count', false);
            
            // Форматируем данные
            $templates = collect($paginator->items())->map(function($template) use ($includeCategory, $includeTvsCount) {
                return $this->formatTemplate($template, $includeCategory, $includeTvsCount);
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
            $template = SiteTemplate::with(['categories', 'tvs'])->find($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }
            
            $formattedTemplate = $this->formatTemplate($template, true, true);
            
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

            $templateData = [
                'templatename' => $validated['templatename'],
                'description' => $validated['description'] ?? '',
                'content' => $validated['content'],
                'category' => $validated['category'] ?? 0,
                'editor_type' => $validated['editor_type'] ?? 0,
                'template_type' => $validated['template_type'] ?? 0,
                'icon' => $validated['icon'] ?? '',
                'locked' => $validated['locked'] ?? false,
                'selectable' => $validated['selectable'] ?? true,
                'templatealias' => $validated['templatealias'] ?? '',
                'templatecontroller' => $validated['templatecontroller'] ?? '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $template = SiteTemplate::create($templateData);

            // Прикрепляем TV параметры
            if (isset($validated['tv_ids']) && is_array($validated['tv_ids'])) {
                $template->tvs()->sync($validated['tv_ids']);
            }

            $formattedTemplate = $this->formatTemplate($template->fresh(), true, true);
            
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
            $template = SiteTemplate::find($id);
                
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

            $updateData = [];
            $fields = [
                'templatename', 'description', 'content', 'category', 'editor_type',
                'template_type', 'icon', 'locked', 'selectable', 'templatealias', 'templatecontroller'
            ];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $updateData['editedon'] = time();

            $template->update($updateData);

            // Обновляем TV параметры
            if (isset($validated['tv_ids'])) {
                $template->tvs()->sync($validated['tv_ids']);
            }

            $formattedTemplate = $this->formatTemplate($template->fresh(), true, true);
            
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
            $template = SiteTemplate::find($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }

            // Проверяем, используется ли шаблон документами
            $documentsCount = \EvolutionCMS\Models\SiteContent::where('template', $id)->count();
            if ($documentsCount > 0) {
                return $this->error(
                    'Cannot delete template with associated documents', 
                    ['template' => "Template is used by {$documentsCount} document(s)"],
                    422
                );
            }

            $template->delete();

            return $this->deleted('Template deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete template');
        }
    }

    public function tvs($id)
    {
        try {
            $template = SiteTemplate::with('tvs')->find($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $tvs = $template->tvs->map(function($tv) {
                return [
                    'id' => $tv->id,
                    'name' => $tv->name,
                    'caption' => $tv->caption,
                    'type' => $tv->type,
                    'rank' => $tv->pivot->rank ?? 0,
                ];
            });

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'tvs' => $tvs,
                'tvs_count' => $tvs->count(),
            ], 'Template TVs retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch template TVs');
        }
    }

    public function addTv(Request $request, $id)
    {
        try {
            $template = SiteTemplate::find($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $validated = $this->validateRequest($request, [
                'tv_id' => 'required|integer|exists:site_tmplvars,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            // Проверяем, не добавлен ли уже TV
            $existingTv = $template->tvs()->where('tmplvarid', $validated['tv_id'])->first();
            if ($existingTv) {
                return $this->error(
                    'TV already attached to template',
                    ['tv' => 'This TV is already attached to the template'],
                    422
                );
            }

            // Прикрепляем TV с указанным рангом
            $template->tvs()->attach($validated['tv_id'], ['rank' => $validated['rank'] ?? 0]);

            $tv = SiteTmplvar::find($validated['tv_id']);

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'tv' => [
                    'id' => $tv->id,
                    'name' => $tv->name,
                    'caption' => $tv->caption,
                    'type' => $tv->type,
                    'rank' => $validated['rank'] ?? 0,
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
            $template = SiteTemplate::find($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $tv = $template->tvs()->where('tmplvarid', $tvId)->first();
            if (!$tv) {
                return $this->notFound('TV not found in template');
            }

            $template->tvs()->detach($tvId);

            return $this->deleted('TV removed from template successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove TV from template');
        }
    }

    public function duplicate($id)
    {
        try {
            $template = SiteTemplate::with('tvs')->find($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            // Создаем копию шаблона
            $newTemplate = $template->replicate();
            $newTemplate->templatename = $template->templatename . ' (Copy)';
            $newTemplate->createdon = time();
            $newTemplate->editedon = time();
            $newTemplate->save();

            // Копируем TV параметры
            if ($template->tvs->count() > 0) {
                $tvData = [];
                foreach ($template->tvs as $tv) {
                    $tvData[$tv->id] = ['rank' => $tv->pivot->rank ?? 0];
                }
                $newTemplate->tvs()->sync($tvData);
            }

            $formattedTemplate = $this->formatTemplate($newTemplate, true, true);
            
            return $this->created($formattedTemplate, 'Template duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate template');
        }
    }

    public function content($id)
    {
        try {
            $template = SiteTemplate::find($id);
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

    protected function formatTemplate($template, $includeCategory = false, $includeTvsCount = false)
    {
        $data = [
            'id' => $template->id,
            'name' => $template->templatename,
            'templatename' => $template->templatename,
            'description' => $template->description,
            'content' => $template->content,
            'editor_type' => $template->editor_type,
            'template_type' => $template->template_type,
            'icon' => $template->icon,
            'locked' => (bool)$template->locked,
            'selectable' => (bool)$template->selectable,
            'templatealias' => $template->templatealias,
            'templatecontroller' => $template->templatecontroller,
            'created_at' => $this->safeFormatDate($template->createdon),
            'updated_at' => $this->safeFormatDate($template->editedon),
            'is_locked' => $template->isAlreadyEdit,
            'locked_info' => $template->alreadyEditInfo,
        ];

        if ($includeCategory && $template->categories) {
            $data['category'] = [
                'id' => $template->categories->id,
                'name' => $template->categories->category,
            ];
        }

        if ($includeTvsCount) {
            $data['tvs_count'] = $template->tvs->count();
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