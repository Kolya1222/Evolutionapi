<?php

namespace roilafx\Evolutionapi\Services\Content;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteContent;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\ClosureTable;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;


class DocumentService extends BaseService
{
    /**
     * Публикация документов - используем готовые scope'ы из модели
     */
    public function publishDocuments(): array
    {
        $time = $this->core->timestamp();
        
        // Используем встроенный scope и массовое обновление
        $count = SiteContent::publishDocuments($time)
            ->update(['published' => 1, 'pub_date' => 0]);
        
        $this->core->clearCache('full');
        
        return [
            'published_count' => $count,
            'timestamp' => $time
        ];
    }

    /**
     * Снятие с публикации - используем готовые scope'ы из модели
     */
    public function unpublishDocuments(): array
    {
        $time = $this->core->timestamp();
        
        $count = SiteContent::unPublishDocuments($time)
            ->update(['published' => 0, 'unpub_date' => 0]);
        
        $this->core->clearCache('full');
        
        return [
            'unpublished_count' => $count,
            'timestamp' => $time
        ];
    }

    public function updateTree(): array
    {
        $start = microtime(true);

        try {
            ClosureTable::truncate();
            $documents = SiteContent::orderBy('parent', 'asc')
                ->orderBy('menuindex', 'asc')
                ->get();
            
            $processedCount = 0;
            $closureRecordsCreated = 0;
            
            foreach ($documents as $document) {
                DB::table('site_content_closure')->insert([
                    'ancestor' => $document->id,
                    'descendant' => $document->id,
                    'depth' => 0
                ]);
                $closureRecordsCreated++;
                
                if ($document->parent > 0) {
                    $ancestors = DB::table('site_content_closure')
                        ->where('descendant', $document->parent)
                        ->get();
                    
                    foreach ($ancestors as $ancestor) {
                        DB::table('site_content_closure')->insert([
                            'ancestor' => $ancestor->ancestor,
                            'descendant' => $document->id,
                            'depth' => $ancestor->depth + 1
                        ]);
                        $closureRecordsCreated++;
                    }
                }
                
                $processedCount++;
            }
            
            $executionTime = round((microtime(true) - $start), 2);
            $totalClosureRecords = DB::table('site_content_closure')->count();
            
            return [
                'execution_time' => $executionTime,
                'processed_documents' => $processedCount,
                'total_documents' => SiteContent::count(),
                'closure_records_created' => $totalClosureRecords,
                'message' => 'Tree structure updated successfully'
            ];
            
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Перемещение документа - используем встроенные методы модели
     */
    public function move(int $documentId, int $newParentId): SiteContent
    {
        $document = $this->getDocument($documentId);

        // Проверка блокировки через встроенное свойство модели
        //if ($document->isAlreadyEdit) {
        //    throw new Exception(
        //        "Document is currently being edited by: " . 
        //        ($document->alreadyEditInfo['username'] ?? 'another user')
        //    );
        //}

        // Используем встроенные проверки модели
        if ($document->getKey() === $newParentId) {
            throw new Exception('Cannot move document to itself');
        }

        // Проверка на перемещение в дочерний документ через встроенные методы
        if ($document->getDescendants()->contains('id', $newParentId)) {
            throw new Exception('Cannot move document to its child');
        }

        // Событие перед перемещением
        $evtOut = $this->core->invokeEvent('OnBeforeMoveDocument', [
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

        // Используем встроенный метод перемещения модели
        $position = $this->getNextPosition($newParentId);
        $document->moveTo($position, $newParentId);

        // Событие после перемещения
        $this->core->invokeEvent('OnAfterMoveDocument', [
            'id' => $document->getKey(),
            'old_parent' => $document->parent,
            'new_parent' => $newParentId
        ]);

        $this->core->clearCache('full');

        return $document->fresh();
    }

    /**
     * Получение документа с проверкой блокировки
     */
    public function getDocument(int $id): SiteContent
    {
        $document = SiteContent::withTrashed()->find($id);
        
        if (!$document) {
            throw new Exception('Document not found');
        }

        return $document;
    }

    /**
     * Создание документа с использованием fillable полей модели
     */
    public function createDocument(array $data): SiteContent
    {
        // Используем fillable поля из модели
        $documentData = array_merge([
            'createdon' => time(),
            'editedon' => time(),
            'createdby' => $this->core->getLoginUserID('mgr'),
            'publishedon' => ($data['published'] ?? true) ? time() : 0,
        ], $data);

        $document = SiteContent::create($documentData);

        // Сохранение TV параметров через отношения модели
        if (isset($data['tv']) && is_array($data['tv'])) {
            $this->saveDocumentTV($document, $data['tv']);
        }

        $this->core->invokeEvent('OnDocFormSave', [
            'mode' => 'new',
            'id' => $document->id
        ]);

        return $document;
    }

    /**
     * Обновление документа с проверкой блокировки
     */
    public function updateDocument(int $id, array $data): SiteContent
    {
        $document = $this->getDocument($id);

        // Проверка блокировки через встроенное свойство
        //if ($document->isAlreadyEdit) {
        //    throw new Exception(
        //        "Document is currently being edited by: " . 
        //        ($document->alreadyEditInfo['username'] ?? 'another user')
        //    );
        //}

        $updateData = array_merge($data, [
            'editedon' => time(),
            'editedby' => $this->core->getLoginUserID('mgr'),
        ]);

        if (isset($data['published']) && $data['published'] && !$document->publishedon) {
            $updateData['publishedon'] = time();
        }

        $document->update($updateData);

        // Обновление TV параметров через отношения
        if (isset($data['tv']) && is_array($data['tv'])) {
            $this->saveDocumentTV($document, $data['tv']);
        }

        $this->core->invokeEvent('OnDocFormSave', [
            'mode' => 'upd',
            'id' => $document->id
        ]);

        $this->core->clearCache('full');

        return $document->fresh();
    }

    /**
     * Удаление документа (soft delete)
     */
    public function deleteDocument(int $id): bool
    {
        $document = $this->getDocument($id);

        // Используем встроенный soft delete модели
        $document->delete();

        $this->core->invokeEvent('OnBeforeEmptyTrash', [
            'ids' => [$id]
        ]);

        $this->core->clearCache('full');

        return true;
    }

    /**
     * Восстановление документа
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

        // Используем встроенное восстановление
        $document->restore();

        $this->core->clearCache('full');

        return $document->fresh();
    }

    /**
     * Получение TV параметров документа через встроенное свойство
     */
    public function getDocumentTV(int $documentId): array
    {
        $document = $this->getDocument($documentId);
        
        return $document->templateValues->mapWithKeys(function($tvValue) {
            $tv = $tvValue->tmplvar;
            return [
                $tv->name => [
                    'value' => $tvValue->value,
                    'tv_id' => $tv->id, // Теперь будет правильный tv_id
                ]
            ];
        })->toArray();
    }

    /**
     * Получение TV параметров с полной информацией
     */
    public function getDocumentTVFull(int $documentId): array
    {
        $document = $this->getDocument($documentId);
        
        return $document->templateValues->map(function($tvValue) {
            $tv = $tvValue->tmplvar;
            return [
                'name' => $tv->name,
                'value' => $this->processTVValue($tvValue->value, $tv->type),
                'tv_id' => $tv->id,
                'caption' => $tv->caption,
                'description' => $tv->description,
                'type' => $tv->type,
                'default_value' => $tv->default_text,
                'elements' => $tv->elements,
            ];
        })->keyBy('name')->toArray();
    }

    public function getDocumentGroups(int $documentId): array
    {
        $document = $this->getDocument($documentId);
        return $document->documentGroups()->get()->all();
    }

    public function attachToGroups(int $documentId, array $groupIds): array
    {
        $document = $this->getDocument($documentId);
        $currentGroupIds = $document->documentGroups->pluck('id')->toArray();
        
        $newGroupIds = array_diff($groupIds, $currentGroupIds);
        
        if (!empty($newGroupIds)) {
            $document->documentGroups()->attach($newGroupIds);
        }
        
        return [
            'added_count' => count($newGroupIds),
            'added_groups' => $newGroupIds
        ];
    }

    public function detachFromGroup(int $documentId, int $groupId): bool
    {
        $document = $this->getDocument($documentId);
        return $document->documentGroups()->detach($groupId) > 0;
    }

    public function syncGroups(int $documentId, array $groupIds): array
    {
        $document = $this->getDocument($documentId);
        $document->documentGroups()->sync($groupIds);

        return [
            'synced_groups' => $groupIds,
            'groups_count' => count($groupIds)
        ];
    }

    /**
     * Сохранение TV параметров через отношения модели
     */
    public function saveDocumentTV(SiteContent $document, array $tvData): void
    {
        foreach ($tvData as $tvName => $tvValue) {
            $tv = SiteTmplvar::where('name', $tvName)->first();
            
            if ($tv) {
                $processedValue = $this->processTVValueForSave($tvValue, $tv->type);
                
                // Используем отношение модели для создания/обновления
                $document->templateValues()->updateOrCreate(
                    ['tmplvarid' => $tv->id],
                    ['value' => $processedValue]
                );
            }
        }
    }

    /**
     * Поиск документов с использованием scope'ов модели
     */
    public function searchDocuments(array $filters = []): LengthAwarePaginator
    {
        $query = SiteContent::query();
        
        if (isset($filters['published'])) {
            $query->published();
        }
        
        if (isset($filters['unpublished'])) {
            $query->unpublished();
        }
        
        if (isset($filters['active'])) {
            $query->active();
        }
        
        //if (isset($filters['without_protected'])) {
        //    $query->withoutProtected();
        //}
        
        if (isset($filters['tv'])) {
            $query->withTVs(array_keys($filters['tv']));
        }
        
        if (isset($filters['tv_filter'])) {
            $query->tvFilter($filters['tv_filter']);
        }
        
        if (isset($filters['tv_order'])) {
            $query->tvOrderBy($filters['tv_order']);
        }
        
        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;
        
        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Получение дочерних документов через встроенные методы
     */
    public function getChildren(int $parentId, array $columns = ['*'])
    {
        $parent = $this->getDocument($parentId);
        return $parent->getChildren($columns);
    }

    /**
     * Получение предков документа
     */
    public function getAncestors(int $documentId)
    {
        $document = $this->getDocument($documentId);
        return $document->getAncestors();
    }

    /**
     * Получение потомков документа
     */
    public function getDescendants(int $documentId)
    {
        $document = $this->getDocument($documentId);
        return $document->getDescendants();
    }

    /**
     * Вспомогательные методы
     */
    private function getNextPosition(int $parentId): int
    {
        return SiteContent::where('parent', $parentId)->count();
    }

    /**
     * Обработка TV значений
     */
    private function processTVValue($value, $type)
    {
        if (empty($value)) return $value;

        if ($type === 'custom_tv:multitv') {
            return $this->parseMultiTV($value);
        }
        
        if ($type === 'number') {
            return is_numeric($value) ? (float)$value : $value;
        }
        
        if (in_array($type, ['list', 'list-multiple', 'checkbox', 'radio']) && str_contains($value, '||')) {
            return explode('||', $value);
        }
        
        if (is_string($value) && $this->isJson($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }
        
        return $value;
    }

    private function processTVValueForSave($value, $type)
    {
        if ($type === 'custom_tv:multitv' || is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        if (in_array($type, ['list-multiple', 'checkbox']) && is_array($value)) {
            return implode('||', $value);
        }
        
        return $value;
    }

    private function parseMultiTV($value)
    {
        if (empty($value)) return [];
        
        $data = json_decode($value, true);
        return $data ?: [];
    }

    private function isJson($string)
    {
        if (!is_string($string)) return false;
        
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}