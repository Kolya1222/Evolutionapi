<?php

namespace EvolutionCMS\Evolutionapi\Services\Content;

use EvolutionCMS\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteContent;
use EvolutionCMS\Models\SiteTmplvarContentvalue;
use EvolutionCMS\Legacy\Permissions;
use Exception;
use Illuminate\Support\Collection;

class DocumentService extends BaseService
{
    /**
     * Проверка прав через Evolution CMS
     * ДОЛЖЕН БЫТЬ PROTECTED для совместимости с BaseService
     */
    protected function hasPermission(string $permission): bool
    {
        return $this->core->hasPermission($permission);
    }

    /**
     * Публикация документов - на основе core/src/Controllers/RefreshSite.php
     */
    public function publishDocuments(): array
    {
        $this->checkPermission('publish_document');

        $time = $this->core->timestamp();
        $query = SiteContent::publishDocuments($time);
        $count = $query->count();
        
        $query->update([
            'published' => 1, 
            'pub_date' => 0
        ]);
        
        $this->core->clearCache('full');
        
        return [
            'published_count' => $count,
            'timestamp' => $time
        ];
    }

    /**
     * Снятие с публикации - на основе core/src/Controllers/RefreshSite.php
     */
    public function unpublishDocuments(): array
    {
        $this->checkPermission('publish_document');

        $time = $this->core->timestamp();
        $query = SiteContent::unPublishDocuments($time);
        $count = $query->count();
        
        $query->update([
            'published' => 0, 
            'unpub_date' => 0
        ]);
        
        $this->core->clearCache('full');
        
        return [
            'unpublished_count' => $count,
            'timestamp' => $time
        ];
    }

    /**
     * Поиск документов - на основе core/src/Controllers/Search.php
     */
    public function search(array $filters = []): Collection
    {
        $this->checkPermission('view_document');

        $query = SiteContent::query()->withTrashed();

        // Применяем фильтры как в Evolution CMS Search
        if (!empty($filters['search'])) {
            $this->applySearchFilter($query, $filters['search']);
        }

        if (!empty($filters['template'])) {
            $query->where('template', $filters['template']);
        }

        if (!empty($filters['parent'])) {
            $query->where('parent', $filters['parent']);
        }

        // Применяем права доступа как в Evolution CMS
        $this->applyPermissionFilters($query);

        return $query->get()->map(function($document) {
            return $this->formatDocumentForSearch($document);
        });
    }

    /**
     * Применяем условия поиска как в Evolution CMS
     */
    private function applySearchFilter($query, string $searchTerm): void
    {
        $query->where(function($q) use ($searchTerm) {
            $q->where('pagetitle', 'LIKE', "%{$searchTerm}%")
              ->orWhere('longtitle', 'LIKE', "%{$searchTerm}%")
              ->orWhere('description', 'LIKE', "%{$searchTerm}%")
              ->orWhere('content', 'LIKE', "%{$searchTerm}%")
              ->orWhere('introtext', 'LIKE', "%{$searchTerm}%")
              ->orWhere('alias', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Применяем фильтры прав доступа как в Evolution CMS
     */
    private function applyPermissionFilters($query): void
    {
        if ($this->core->getConfig('use_udperms')) {
            $mgrRole = $_SESSION['mgrRole'] ?? 0;
            
            if ($mgrRole != 1) {
                if (isset($_SESSION['mgrDocgroups']) && is_array($_SESSION['mgrDocgroups'])) {
                    $query->leftJoin('document_groups', 'site_content.id', '=', 'document_groups.document')
                        ->where(function ($q) {
                            $q->where('privatemgr', 0)
                              ->orWhereIn('document_group', $_SESSION['mgrDocgroups']);
                        });
                } else {
                    $query->where('privatemgr', 0);
                }
            }
        }
    }

    /**
     * Обновление дерева документов - на основе core/src/Controllers/UpdateTree.php
     */
    public function updateTree(): array
    {
        $this->checkPermission('edit_document');

        $start = microtime(true);
        
        // Очищаем таблицу замыканий как в Evolution CMS
        \EvolutionCMS\Models\ClosureTable::query()->truncate();
        
        // Рекурсивно строим дерево как в Evolution CMS
        $result = SiteContent::query()->where('parent', 0)->get();
        $processed = 0;
        
        while($result->count() > 0) {
            $parents = [];
            foreach ($result as $item) {
                $descendant = $item->getKey();
                $ancestor = $item->parent ?: $descendant;
                
                $closure = new \EvolutionCMS\Models\ClosureTable();
                $closure->insertNode($ancestor, $descendant);
                $parents[] = $descendant;
                $processed++;
            }
            $result = SiteContent::query()->whereIn('parent', $parents)->get();
        }
        
        $executionTime = round((microtime(true) - $start), 2);
        
        return [
            'execution_time' => $executionTime,
            'total_documents' => SiteContent::query()->count(),
            'processed_documents' => $processed
        ];
    }

    /**
     * Получение документа с проверкой прав - на основе core/src/Controllers/MoveDocument.php
     */
    public function getDocument(int $id): SiteContent
    {
        $document = SiteContent::withTrashed()->find($id);
        
        if (!$document) {
            throw new Exception('Document not found');
        }

        // Проверяем права как в Evolution CMS
        $this->checkDocumentPermission($document, 'view_document');

        return $document;
    }

    /**
     * Создание документа - на основе анализа использования в Evolution CMS
     */
    public function createDocument(array $data): SiteContent
    {
        $this->checkPermission('new_document');

        $currentTimestamp = time();

        // Подготовка данных как в Evolution CMS
        $documentData = [
            'pagetitle' => $data['pagetitle'],
            'parent' => $data['parent'],
            'template' => $data['template'],
            'content' => $data['content'] ?? '',
            'alias' => $data['alias'] ?? '',
            'menuindex' => $data['menuindex'] ?? 0,
            'published' => $data['published'] ?? false,
            'isfolder' => $data['isfolder'] ?? false,
            'type' => $data['type'] ?? 'document',
            'contentType' => $data['contentType'] ?? 'text/html',
            'description' => $data['description'] ?? '',
            'longtitle' => $data['longtitle'] ?? '',
            'introtext' => $data['introtext'] ?? '',
            'richtext' => $data['richtext'] ?? true,
            'searchable' => $data['searchable'] ?? true,
            'cacheable' => $data['cacheable'] ?? true,
            'hidemenu' => $data['hidemenu'] ?? false,
            'createdon' => $currentTimestamp,
            'editedon' => $currentTimestamp,
            'createdby' => $this->core->getLoginUserID('mgr'),
        ];

        $document = SiteContent::create($documentData);

        // Вызываем события как в Evolution CMS
        $this->invokeEvent('OnDocFormSave', [
            'mode' => 'new',
            'id' => $document->id
        ]);

        return $document;
    }

    /**
     * Обновление документа - на основе анализа использования в Evolution CMS
     */
    public function updateDocument(int $id, array $data): SiteContent
    {
        $document = $this->getDocument($id);
        $this->checkDocumentPermission($document, 'save_document');

        $updateData = [];
        $allowedFields = [
            'pagetitle', 'parent', 'template', 'content', 'alias', 'menuindex',
            'published', 'isfolder', 'type', 'contentType', 'description',
            'longtitle', 'introtext', 'richtext', 'searchable', 'cacheable', 'hidemenu'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        $updateData['editedon'] = time();
        $updateData['editedby'] = $this->core->getLoginUserID('mgr');

        // Обновляем publishedon если статус публикации изменился
        if (isset($data['published']) && $data['published'] && !$document->publishedon) {
            $updateData['publishedon'] = time();
        }

        $document->update($updateData);

        // Вызываем события как в Evolution CMS
        $this->invokeEvent('OnDocFormSave', [
            'mode' => 'upd',
            'id' => $document->id
        ]);

        $this->core->clearCache('full');

        return $document->fresh();
    }

    /**
     * Удаление документа (мягкое) - на основе анализа использования в Evolution CMS
     */
    public function deleteDocument(int $id): bool
    {
        $document = $this->getDocument($id);
        $this->checkDocumentPermission($document, 'delete_document');

        $document->update([
            'deleted' => 1,
            'deletedon' => time(),
            'deletedby' => $this->core->getLoginUserID('mgr'),
            'editedon' => time(),
        ]);

        $this->invokeEvent('OnBeforeEmptyTrash', [
            'ids' => [$id]
        ]);

        $this->core->clearCache('full');

        return true;
    }

    /**
     * Восстановление документа - на основе анализа использования в Evolution CMS
     */
    public function restoreDocument(int $id): SiteContent
    {
        $document = SiteContent::withTrashed()
            ->where('id', $id)
            ->where('deleted', 1)
            ->first();

        if (!$document) {
            throw new Exception('Document not found or not deleted');
        }

        $this->checkDocumentPermission($document, 'delete_document');

        $document->update([
            'deleted' => 0,
            'deletedon' => 0,
            'deletedby' => 0,
            'editedon' => time(),
        ]);

        $this->core->clearCache('full');

        return $document->fresh();
    }

    /**
     * Проверка прав на конкретный документ - на основе core/src/Legacy/Permissions.php
     */
    private function checkDocumentPermission(SiteContent $document, string $permission): void
    {
        // Используем checkPermission из BaseService вместо дублирования
        $this->checkPermission($permission);

        // Дополнительная проверка прав на конкретный документ
        if ($this->core->getConfig('use_udperms')) {
            $udperms = new Permissions();
            $udperms->user = $this->core->getLoginUserID('mgr');
            $udperms->document = $document->id;
            $udperms->role = $_SESSION['mgrRole'] ?? 0;

            if (!$udperms->checkPermissions()) {
                throw new Exception('Access denied to document');
            }
        }
    }

    /**
     * Форматирование документа для поиска - на основе Evolution CMS
     */
    private function formatDocumentForSearch(SiteContent $document): array
    {
        return [
            'id' => $document->id,
            'pagetitle' => $document->pagetitle,
            'longtitle' => $document->longtitle,
            'description' => $document->description,
            'alias' => $document->alias,
            'published' => (bool)$document->published,
            'deleted' => (bool)$document->deleted,
            'isfolder' => (bool)$document->isfolder,
            'template' => $document->template,
            'menuindex' => $document->menuindex,
            'createdon' => $this->safeFormatDate($document->createdon),
            'editedon' => $this->safeFormatDate($document->editedon),
        ];
    }

    /**
     * Перемещение документа (добавляем недостающий метод)
     */
    public function move(int $documentId, int $newParentId): SiteContent
    {
        $this->checkPermission('save_document');

        // Валидация
        if ($documentId === $newParentId) {
            throw new Exception('Cannot move document to itself');
        }

        if ($documentId <= 0 || $newParentId < 0) {
            throw new Exception('Invalid document or parent ID');
        }

        $document = $this->getDocument($documentId);

        // Проверка на перемещение в дочерний документ
        $parents = $this->core->getParentIds($newParentId);
        if (in_array($document->getKey(), $parents, true)) {
            throw new Exception('Cannot move document to its child');
        }

        // Проверка прав на новый родительский документ
        if ($this->core->getConfig('use_udperms') && $document->parent !== $newParentId) {
            $this->checkNewParentPermission($newParentId);
        }

        // Событие перед перемещением
        $evtOut = $this->invokeEvent('OnBeforeMoveDocument', [
            'id' => $document->getKey(),
            'old_parent' => $document->parent,
            'new_parent' => $newParentId
        ]);

        if (is_array($evtOut) && count($evtOut) > 0) {
            $newParent = (int)array_pop($evtOut);
            if ($newParent === $document->parent) {
                throw new Exception('Move cancelled by OnBeforeMoveDocument event');
            } else {
                $newParentId = $newParent;
            }
        }

        // Логика перемещения
        if ($newParentId > 0) {
            $parentDocument = $this->getDocument($newParentId);
            
            if ($parentDocument->deleted) {
                throw new Exception('Parent document is deleted');
            }

            $children = $this->getAllChildren($document->getKey());
            if (in_array($parentDocument->getKey(), $children, true)) {
                throw new Exception('Cannot move a document to a child document');
            }

            // Устанавливаем isfolder для родителя
            $parentDocument->isfolder = true;
            $parentDocument->save();

            // Обновляем isfolder для старого родителя
            if ($document->ancestor && $document->ancestor->children()->count() <= 1) {
                $document->ancestor->isfolder = false;
                $document->ancestor->save();
            }

            $document->parent = $parentDocument->getKey();
        } else {
            $document->parent = 0;
        }

        $document->save();

        // Событие после перемещения
        $this->invokeEvent('OnAfterMoveDocument', [
            'id' => $document->getKey(),
            'old_parent' => $document->parent,
            'new_parent' => $newParentId
        ]);

        // Очистка кэша
        $this->core->clearCache('full');

        $this->logManagerAction('move_document', $document->getKey(), $document->pagetitle);

        return $document->fresh();
    }

    /**
     * Проверка прав на родительский документ
     */
    private function checkNewParentPermission($id): void
    {
        $udperms = new Permissions();
        $udperms->user = $this->core->getLoginUserID('mgr');
        $udperms->document = $id;
        $udperms->role = $_SESSION['mgrRole'];

        if (!$udperms->checkPermissions()) {
            throw new Exception('Access denied to parent document');
        }
    }

    /**
     * Получение всех дочерних документов
     */
    private function getAllChildren($parentId): array
    {
        return $this->core->getAllChildren($parentId);
    }
}