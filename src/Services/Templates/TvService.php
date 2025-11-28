<?php

namespace roilafx\Evolutionapi\Services\Templates;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\SiteTmplvarAccess;
use EvolutionCMS\Models\SiteTemplate;
use EvolutionCMS\Models\DocumentgroupName;
use Exception;

class TvService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SiteTmplvar::query();

        // Поиск по названию, caption или описанию
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('caption', 'LIKE', "%{$searchTerm}%")
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

        // Фильтр по типу
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?SiteTmplvar
    {
        return SiteTmplvar::with(['categories', 'templates', 'tmplvarAccess'])->find($id);
    }

    public function create(array $data): SiteTmplvar
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
            $this->invokeEvent('OnBeforeTVFormSave', $eventParams);

            // Проверяем, не отменило ли событие сохранение
            if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
                throw new Exception('TV creation cancelled by event');
            }

            $tvData = [
                'name' => $data['name'],
                'caption' => $data['caption'],
                'type' => $data['type'],
                'description' => $data['description'] ?? '',
                'category' => $data['category'] ?? 0,
                'editor_type' => $data['editor_type'] ?? 0,
                'locked' => $data['locked'] ?? false,
                'elements' => $data['elements'] ?? '',
                'rank' => $data['rank'] ?? 0,
                'display' => $data['display'] ?? '',
                'display_params' => $data['display_params'] ?? '',
                'default_text' => $data['default_text'] ?? '',
                'properties' => isset($data['properties']) ? json_encode($data['properties']) : '',
                'createdon' => time(),
                'editedon' => time(),
            ];

            $tv = SiteTmplvar::create($tvData);

            // Прикрепляем к шаблонам
            if (isset($data['template_ids']) && is_array($data['template_ids'])) {
                $templateData = [];
                foreach ($data['template_ids'] as $templateId) {
                    $templateData[$templateId] = ['rank' => 0];
                }
                $tv->templates()->sync($templateData);
            }

            // Настраиваем доступ к группам документов
            if (isset($data['document_group_ids']) && is_array($data['document_group_ids'])) {
                foreach ($data['document_group_ids'] as $groupId) {
                    SiteTmplvarAccess::create([
                        'tmplvarid' => $tv->id,
                        'documentgroup' => $groupId,
                    ]);
                }
            }

            // Вызов события после сохранения
            $this->invokeEvent('OnTVFormSave', [
                'mode' => $mode,
                'id' => $tv->id,
                'tv' => $tv
            ]);

            // Логирование действия менеджера
            $this->logManagerAction('tv_new', $tv->id, $tv->name);

            return $tv->fresh(['categories', 'templates', 'tmplvarAccess']);

        } finally {
            // Восстанавливаем оригинальный $_POST
            $_POST = $originalPost;
        }
    }

    public function update(int $id, array $data): SiteTmplvar
    {
        $tv = $this->findById($id);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        // Проверяем блокировку
        if ($tv->isAlreadyEdit) {
            $lockInfo = $tv->alreadyEditInfo;
            throw new Exception(
                "TV is currently being edited by: " . 
                ($lockInfo['username'] ?? 'another user')
            );
        }

        // Устанавливаем блокировку перед редактированием
        $this->core->lockElement(2, $id); // 2 - тип TV

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
            $this->invokeEvent('OnBeforeTVFormSave', $eventParams);

            // Проверяем, не отменило ли событие сохранение
            if (isset($eventParams['allowSave']) && !$eventParams['allowSave']) {
                $this->core->unlockElement(2, $id);
                throw new Exception('TV update cancelled by event');
            }

            $updateData = [];
            $fields = [
                'name', 'caption', 'type', 'description', 'category', 'editor_type',
                'locked', 'elements', 'rank', 'display', 'display_params', 'default_text'
            ];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (isset($data['properties'])) {
                $updateData['properties'] = json_encode($data['properties']);
            }

            $updateData['editedon'] = time();

            $tv->update($updateData);

            // Обновляем связи с шаблонами
            if (isset($data['template_ids'])) {
                $templateData = [];
                foreach ($data['template_ids'] as $templateId) {
                    $templateData[$templateId] = ['rank' => 0];
                }
                $tv->templates()->sync($templateData);
            }

            // Обновляем доступ к группам документов
            if (isset($data['document_group_ids'])) {
                // Удаляем старый доступ
                SiteTmplvarAccess::where('tmplvarid', $id)->delete();
                
                // Добавляем новый доступ
                foreach ($data['document_group_ids'] as $groupId) {
                    SiteTmplvarAccess::create([
                        'tmplvarid' => $id,
                        'documentgroup' => $groupId,
                    ]);
                }
            }

            // Вызов события после сохранения
            $this->invokeEvent('OnTVFormSave', [
                'mode' => $mode,
                'id' => $tv->id,
                'tv' => $tv
            ]);

            $this->logManagerAction('tv_save', $tv->id, $tv->name);

            return $tv->fresh(['categories', 'templates', 'tmplvarAccess']);

        } finally {
            // Восстанавливаем оригинальный $_POST и снимаем блокировку
            $_POST = $originalPost;
            $this->core->unlockElement(2, $id);
        }
    }

    public function delete(int $id): bool
    {
        $tv = $this->findById($id);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        // Проверяем блокировку
        if ($tv->isAlreadyEdit) {
            throw new Exception('TV is locked and cannot be deleted');
        }

        // Вызов события перед удалением
        $this->invokeEvent('OnBeforeTVFormDelete', [
            'id' => $tv->id,
            'tv' => $tv
        ]);

        $tv->delete();

        // Вызов события после удаления
        $this->invokeEvent('OnTVFormDelete', [
            'id' => $tv->id,
            'tv' => $tv
        ]);

        $this->logManagerAction('tv_delete', $tv->id, $tv->name);

        return true;
    }

    public function getTvTemplates(int $tvId): array
    {
        $tv = $this->findById($tvId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        return $tv->templates->map(function($template) {
            return [
                'id' => $template->id,
                'name' => $template->templatename,
                'description' => $template->description,
                'rank' => $template->pivot->rank ?? 0,
            ];
        })->toArray();
    }

    public function addTemplateToTv(int $tvId, int $templateId, int $rank = 0): array
    {
        $tv = $this->findById($tvId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        $template = SiteTemplate::find($templateId);
        if (!$template) {
            throw new Exception('Template not found');
        }

        // Проверяем, не добавлен ли уже шаблон
        $existingTemplate = $tv->templates()->where('templateid', $templateId)->first();
        if ($existingTemplate) {
            throw new Exception('Template already attached to TV');
        }

        // Прикрепляем шаблон с указанным рангом
        $tv->templates()->attach($templateId, ['rank' => $rank]);

        $this->logManagerAction('tv_add_template', $tv->id, $tv->name);

        return [
            'template' => $template,
            'rank' => $rank,
        ];
    }

    public function removeTemplateFromTv(int $tvId, int $templateId): bool
    {
        $tv = $this->findById($tvId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        $template = $tv->templates()->where('templateid', $templateId)->first();
        if (!$template) {
            throw new Exception('Template not found in TV');
        }

        $tv->templates()->detach($templateId);

        $this->logManagerAction('tv_remove_template', $tv->id, $tv->name);

        return true;
    }

    public function getTvAccess(int $tvId): array
    {
        $tv = $this->findById($tvId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        return $tv->tmplvarAccess->map(function($access) {
            return [
                'id' => $access->id,
                'document_group_id' => $access->documentgroup,
                'document_group_name' => $access->documentGroup->name ?? 'Unknown',
            ];
        })->toArray();
    }

    public function addAccessToTv(int $tvId, int $documentGroupId): array
    {
        $tv = $this->findById($tvId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        $documentGroup = DocumentgroupName::find($documentGroupId);
        if (!$documentGroup) {
            throw new Exception('Document group not found');
        }

        // Проверяем, не добавлен ли уже доступ
        $existingAccess = SiteTmplvarAccess::where('tmplvarid', $tvId)
            ->where('documentgroup', $documentGroupId)
            ->first();

        if ($existingAccess) {
            throw new Exception('Access already exists for this document group');
        }

        // Добавляем доступ
        $access = SiteTmplvarAccess::create([
            'tmplvarid' => $tvId,
            'documentgroup' => $documentGroupId,
        ]);

        $this->logManagerAction('tv_add_access', $tv->id, $tv->name);

        return [
            'access' => $access,
            'document_group' => $documentGroup
        ];
    }

    public function removeAccessFromTv(int $tvId, int $accessId): bool
    {
        $tv = $this->findById($tvId);
        if (!$tv) {
            throw new Exception('TV not found');
        }

        $access = SiteTmplvarAccess::where('tmplvarid', $tvId)
            ->where('id', $accessId)
            ->first();

        if (!$access) {
            throw new Exception('Access not found');
        }

        $access->delete();

        $this->logManagerAction('tv_remove_access', $tv->id, $tv->name);

        return true;
    }

    public function duplicate(int $id): SiteTmplvar
    {
        $tv = SiteTmplvar::with(['templates', 'tmplvarAccess'])->find($id);
        if (!$tv) {
            throw new Exception('TV not found');
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

        $this->logManagerAction('tv_duplicate', $newTv->id, $newTv->name);

        return $newTv->fresh(['categories', 'templates', 'tmplvarAccess']);
    }

    public function formatTv(SiteTmplvar $tv, bool $includeCategory = false, bool $includeTemplatesCount = false, bool $includeAccessCount = false): array
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
}