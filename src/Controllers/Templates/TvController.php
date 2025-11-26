<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Templates;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\SiteTmplvarAccess;
use EvolutionCMS\Models\SiteTmplvarTemplate;
use EvolutionCMS\Models\SiteTemplate;
use EvolutionCMS\Models\DocumentgroupName;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TvController extends ApiController
{
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

            $query = SiteTmplvar::query();

            // Поиск по названию, caption или описанию
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('caption', 'LIKE', "%{$searchTerm}%")
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

            // Фильтр по типу
            if ($request->has('type')) {
                $query->where('type', $validated['type']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCategory = $request->get('include_category', false);
            $includeTemplatesCount = $request->get('include_templates_count', false);
            $includeAccessCount = $request->get('include_access_count', false);
            
            // Форматируем данные
            $tvs = collect($paginator->items())->map(function($tv) use ($includeCategory, $includeTemplatesCount, $includeAccessCount) {
                return $this->formatTv($tv, $includeCategory, $includeTemplatesCount, $includeAccessCount);
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
            $tv = SiteTmplvar::with(['categories', 'templates', 'tmplvarAccess'])->find($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }
            
            $formattedTv = $this->formatTv($tv, true, true, true);
            
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

            $tvData = [
                'name' => $validated['name'],
                'caption' => $validated['caption'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? '',
                'category' => $validated['category'] ?? 0,
                'editor_type' => $validated['editor_type'] ?? 0,
                'locked' => $validated['locked'] ?? false,
                'elements' => $validated['elements'] ?? '',
                'rank' => $validated['rank'] ?? 0,
                'display' => $validated['display'] ?? '',
                'display_params' => $validated['display_params'] ?? '',
                'default_text' => $validated['default_text'] ?? '',
                'properties' => isset($validated['properties']) ? json_encode($validated['properties']) : '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $tv = SiteTmplvar::create($tvData);

            // Прикрепляем к шаблонам
            if (isset($validated['template_ids']) && is_array($validated['template_ids'])) {
                $templateData = [];
                foreach ($validated['template_ids'] as $templateId) {
                    $templateData[$templateId] = ['rank' => 0];
                }
                $tv->templates()->sync($templateData);
            }

            // Настраиваем доступ к группам документов
            if (isset($validated['document_group_ids']) && is_array($validated['document_group_ids'])) {
                foreach ($validated['document_group_ids'] as $groupId) {
                    SiteTmplvarAccess::create([
                        'tmplvarid' => $tv->id,
                        'documentgroup' => $groupId,
                    ]);
                }
            }

            $formattedTv = $this->formatTv($tv->fresh(), true, true, true);
            
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
            $tv = SiteTmplvar::find($id);
                
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

            $updateData = [];
            $fields = [
                'name', 'caption', 'type', 'description', 'category', 'editor_type',
                'locked', 'elements', 'rank', 'display', 'display_params', 'default_text'
            ];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            if (isset($validated['properties'])) {
                $updateData['properties'] = json_encode($validated['properties']);
            }

            $updateData['editedon'] = time();

            $tv->update($updateData);

            // Обновляем связи с шаблонами
            if (isset($validated['template_ids'])) {
                $templateData = [];
                foreach ($validated['template_ids'] as $templateId) {
                    $templateData[$templateId] = ['rank' => 0];
                }
                $tv->templates()->sync($templateData);
            }

            // Обновляем доступ к группам документов
            if (isset($validated['document_group_ids'])) {
                // Удаляем старый доступ
                SiteTmplvarAccess::where('tmplvarid', $id)->delete();
                
                // Добавляем новый доступ
                foreach ($validated['document_group_ids'] as $groupId) {
                    SiteTmplvarAccess::create([
                        'tmplvarid' => $id,
                        'documentgroup' => $groupId,
                    ]);
                }
            }

            $formattedTv = $this->formatTv($tv->fresh(), true, true, true);
            
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
            $tv = SiteTmplvar::find($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $tv->delete();

            return $this->deleted('TV deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV');
        }
    }

    public function templates($id)
    {
        try {
            $tv = SiteTmplvar::with('templates')->find($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $templates = $tv->templates->map(function($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->templatename,
                    'description' => $template->description,
                    'rank' => $template->pivot->rank ?? 0,
                ];
            });

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'templates' => $templates,
                'templates_count' => $templates->count(),
            ], 'TV templates retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV templates');
        }
    }

    public function addTemplate(Request $request, $id)
    {
        try {
            $tv = SiteTmplvar::find($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'template_id' => 'required|integer|exists:site_templates,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            // Проверяем, не добавлен ли уже шаблон
            $existingTemplate = $tv->templates()->where('templateid', $validated['template_id'])->first();
            if ($existingTemplate) {
                return $this->error(
                    'Template already attached to TV',
                    ['template' => 'This template is already attached to the TV'],
                    422
                );
            }

            // Прикрепляем шаблон с указанным рангом
            $tv->templates()->attach($validated['template_id'], ['rank' => $validated['rank'] ?? 0]);

            $template = SiteTemplate::find($validated['template_id']);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'template' => [
                    'id' => $template->id,
                    'name' => $template->templatename,
                    'description' => $template->description,
                    'rank' => $validated['rank'] ?? 0,
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
            $tv = SiteTmplvar::find($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $template = $tv->templates()->where('templateid', $templateId)->first();
            if (!$template) {
                return $this->notFound('Template not found in TV');
            }

            $tv->templates()->detach($templateId);

            return $this->deleted('Template removed from TV successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove template from TV');
        }
    }

    public function access($id)
    {
        try {
            $tv = SiteTmplvar::with('tmplvarAccess.documentGroup')->find($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $access = $tv->tmplvarAccess->map(function($access) {
                return [
                    'id' => $access->id,
                    'document_group_id' => $access->documentgroup,
                    'document_group_name' => $access->documentGroup->name ?? 'Unknown',
                ];
            });

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'access' => $access,
                'access_count' => $access->count(),
            ], 'TV access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV access');
        }
    }

    public function addAccess(Request $request, $id)
    {
        try {
            $tv = SiteTmplvar::find($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'document_group_id' => 'required|integer|exists:documentgroup_names,id',
            ]);

            // Проверяем, не добавлен ли уже доступ
            $existingAccess = SiteTmplvarAccess::where('tmplvarid', $id)
                ->where('documentgroup', $validated['document_group_id'])
                ->first();

            if ($existingAccess) {
                return $this->error(
                    'Access already exists for this document group',
                    ['access' => 'This document group already has access to the TV'],
                    422
                );
            }

            // Добавляем доступ
            SiteTmplvarAccess::create([
                'tmplvarid' => $id,
                'documentgroup' => $validated['document_group_id'],
            ]);

            $documentGroup = DocumentgroupName::find($validated['document_group_id']);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'access' => [
                    'document_group_id' => $documentGroup->id,
                    'document_group_name' => $documentGroup->name,
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
            $tv = SiteTmplvar::find($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $access = SiteTmplvarAccess::where('tmplvarid', $id)
                ->where('id', $accessId)
                ->first();

            if (!$access) {
                return $this->notFound('Access not found');
            }

            $access->delete();

            return $this->deleted('Access removed from TV successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove access from TV');
        }
    }

    public function duplicate($id)
    {
        try {
            $tv = SiteTmplvar::with(['templates', 'tmplvarAccess'])->find($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            // Создаем копию TV
            $newTv = $tv->replicate();
            $newTv->name = $tv->name . ' (Copy)';
            $newTv->createdon = time();
            $newTv->editedon = time();
            $newTv->save();

            // Копируем связи с шаблонами
            if ($tv->templates->count() > 0) {
                $templateData = [];
                foreach ($tv->templates as $template) {
                    $templateData[$template->id] = ['rank' => $template->pivot->rank ?? 0];
                }
                $newTv->templates()->sync($templateData);
            }

            // Копируем доступ к группам документов
            if ($tv->tmplvarAccess->count() > 0) {
                foreach ($tv->tmplvarAccess as $access) {
                    SiteTmplvarAccess::create([
                        'tmplvarid' => $newTv->id,
                        'documentgroup' => $access->documentgroup,
                    ]);
                }
            }

            $formattedTv = $this->formatTv($newTv, true, true, true);
            
            return $this->created($formattedTv, 'TV duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate TV');
        }
    }

    protected function formatTv($tv, $includeCategory = false, $includeTemplatesCount = false, $includeAccessCount = false)
    {
        $data = [
            'id' => $tv->id,
            'name' => $tv->name,
            'caption' => $tv->caption,
            'type' => $tv->type,
            'description' => $tv->description,
            'editor_type' => $tv->editor_type,
            'locked' => (bool)$tv->locked,
            'elements' => $tv->elements,
            'rank' => $tv->rank,
            'display' => $tv->display,
            'display_params' => $tv->display_params,
            'default_text' => $tv->default_text,
            'properties' => $tv->properties ? json_decode($tv->properties, true) : [],
            'created_at' => $this->safeFormatDate($tv->createdon),
            'updated_at' => $this->safeFormatDate($tv->editedon),
            'is_locked' => $tv->isAlreadyEdit,
            'locked_info' => $tv->alreadyEditInfo,
        ];

        if ($includeCategory && $tv->categories) {
            $data['category'] = [
                'id' => $tv->categories->id,
                'name' => $tv->categories->category,
            ];
        }

        if ($includeTemplatesCount) {
            $data['templates_count'] = $tv->templates->count();
        }

        if ($includeAccessCount) {
            $data['access_count'] = $tv->tmplvarAccess->count();
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