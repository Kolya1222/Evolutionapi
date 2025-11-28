<?php

namespace roilafx\Evolutionapi\Services\Templates;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteTemplate;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\SiteContent;
use Exception;

class TemplateService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SiteTemplate::query();

        // Поиск по названию или описанию
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('templatename', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Фильтр по категории
        if (!empty($params['category'])) {
            $query->where('category', $params['category']);
        }

        // Фильтр по блокировке
        if (isset($params['locked'])) {
            $query->where('locked', $params['locked']);
        }

        // Фильтр по selectable
        if (isset($params['selectable'])) {
            $query->where('selectable', $params['selectable']);
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'templatename';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?SiteTemplate
    {
        return SiteTemplate::with(['categories', 'tvs'])->find($id);
    }

    public function create(array $data): SiteTemplate
    {
        $mode = 'new';
        $id = 0;

        // Сохраняем оригинальный $_POST и временно подменяем
        $originalPost = $_POST;
        $_POST = $data;

        try {
            // Вызов события перед сохранением
            $eventParams = [
                'mode' => $mode,
                'id' => $id,
            ];
            $this->invokeEvent('OnBeforeTempFormSave', $eventParams);

            // Проверяем, не отменило ли событие сохранение
            if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
                throw new Exception('Template creation cancelled by event');
            }

            $templateData = [
                'templatename' => $data['templatename'],
                'description' => $data['description'] ?? '',
                'content' => $data['content'],
                'category' => $data['category'] ?? 0,
                'editor_type' => $data['editor_type'] ?? 0,
                'template_type' => $data['template_type'] ?? 0,
                'icon' => $data['icon'] ?? '',
                'locked' => $data['locked'] ?? false,
                'selectable' => $data['selectable'] ?? true,
                'templatealias' => $data['templatealias'] ?? '',
                'templatecontroller' => $data['templatecontroller'] ?? '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $template = SiteTemplate::create($templateData);

            // Прикрепляем TV параметры
            if (isset($data['tv_ids']) && is_array($data['tv_ids'])) {
                $template->tvs()->sync($data['tv_ids']);
            }

            // Вызов события после сохранения
            $this->invokeEvent('OnTempFormSave', [
                'mode' => $mode,
                'id' => $template->id,
                'template' => $template
            ]);

            // Логирование действия менеджера
            $this->logManagerAction('template_create', $template->id, $template->templatename);

            return $template;
        } finally {
            // Восстанавливаем оригинальный $_POST
            $_POST = $originalPost;
        }
    }

    public function update(int $id, array $data): SiteTemplate
    {
        $template = $this->findById($id);
        if (!$template) {
            throw new Exception('Template not found');
        }

        // Проверяем блокировку
        if ($template->isAlreadyEdit) {
            $lockInfo = $template->alreadyEditInfo;
            throw new Exception(
                "Template is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку перед редактированием
        $this->core->lockElement(1, $id); // 1 - тип шаблона

        $mode = 'upd';

        // Сохраняем оригинальный $_POST и временно подменяем
        $originalPost = $_POST;
        $_POST = $data;

        try {
            // Вызов события перед сохранением
            $eventParams = [
                'mode' => $mode,
                'id' => $id,
            ];
            $this->invokeEvent('OnBeforeTempFormSave', $eventParams);

            // Проверяем, не отменило ли событие сохранение
            if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
                $this->core->unlockElement(1, $id);
                throw new Exception('Template update cancelled by event');
            }

            $updateData = [];
            $fields = [
                'templatename', 'description', 'content', 'category', 'editor_type',
                'template_type', 'icon', 'locked', 'selectable', 'templatealias', 'templatecontroller'
            ];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            $updateData['editedon'] = time();

            $template->update($updateData);

            // Обновляем TV параметры
            if (isset($data['tv_ids'])) {
                $template->tvs()->sync($data['tv_ids']);
            }

            // Вызов события после сохранения
            $this->invokeEvent('OnTempFormSave', [
                'mode' => $mode,
                'id' => $template->id,
                'template' => $template
            ]);

            $this->logManagerAction('template_save', $template->id, $template->templatename);

            return $template->fresh();
        } finally {
            // Восстанавливаем оригинальный $_POST и снимаем блокировку
            $_POST = $originalPost;
            $this->core->unlockElement(1, $id);
        }
    }

    public function delete(int $id): bool
    {
        $template = $this->findById($id);
        if (!$template) {
            throw new Exception('Template not found');
        }

        // Проверяем блокировку
        if ($template->isAlreadyEdit) {
            throw new Exception('Template is locked and cannot be deleted');
        }

        // Проверяем, используется ли шаблон документами
        $documentsCount = SiteContent::where('template', $id)->count();
        if ($documentsCount > 0) {
            throw new Exception("Cannot delete template with {$documentsCount} associated documents");
        }

        // Вызов события перед удалением
        $this->invokeEvent('OnBeforeTempFormDelete', [
            'id' => $template->id,
            'template' => $template
        ]);

        $template->delete();

        // Вызов события после удаления
        $this->invokeEvent('OnTempFormDelete', [
            'id' => $template->id,
            'template' => $template
        ]);

        $this->logManagerAction('template_delete', $template->id, $template->templatename);

        return true;
    }

    public function getTemplateTvs(int $templateId): array
    {
        $template = $this->findById($templateId);
        if (!$template) {
            throw new Exception('Template not found');
        }

        return $template->tvs->map(function($tv) {
            return [
                'id' => $tv->id,
                'name' => $tv->name,
                'caption' => $tv->caption,
                'type' => $tv->type,
                'rank' => $tv->pivot->rank ?? 0,
            ];
        })->toArray();
    }

    public function addTvToTemplate(int $templateId, int $tvId, int $rank = 0): array
    {
        $template = $this->findById($templateId);
        if (!$template) {
            throw new Exception('Template not found');
        }

        $tv = SiteTmplvar::find($tvId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        // Проверяем, не добавлен ли уже TV
        $existingTv = $template->tvs()->where('tmplvarid', $tvId)->first();
        if ($existingTv) {
            throw new Exception('TV already attached to template');
        }

        // Прикрепляем TV с указанным рангом
        $template->tvs()->attach($tvId, ['rank' => $rank]);

        $this->logManagerAction('template_add_tv', $template->id, $template->templatename);

        return [
            'tv' => $tv,
            'rank' => $rank,
        ];
    }

    public function removeTvFromTemplate(int $templateId, int $tvId): bool
    {
        $template = $this->findById($templateId);
        if (!$template) {
            throw new Exception('Template not found');
        }

        $tv = $template->tvs()->where('tmplvarid', $tvId)->first();
        if (!$tv) {
            throw new Exception('TV not found in template');
        }

        $template->tvs()->detach($tvId);

        $this->logManagerAction('template_remove_tv', $template->id, $template->templatename);

        return true;
    }

    public function duplicate(int $id): SiteTemplate
    {
        $template = SiteTemplate::with('tvs')->find($id);
        if (!$template) {
            throw new Exception('Template not found');
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

        $this->logManagerAction('template_duplicate', $newTemplate->id, $newTemplate->templatename);

        return $newTemplate;
    }

    public function getTemplateContent(int $id): SiteTemplate
    {
        $template = $this->findById($id);
        if (!$template) {
            throw new Exception('Template not found');
        }

        return $template;
    }

    public function formatTemplate(SiteTemplate $template, bool $includeCategory = false, bool $includeTvsCount = false): array
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
}