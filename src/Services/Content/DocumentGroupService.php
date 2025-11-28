<?php

namespace roilafx\Evolutionapi\Services\Content;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\DocumentgroupName;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Pagination\LengthAwarePaginator;

class DocumentGroupService extends BaseService
{
    public function getAll(array $params = []): LengthAwarePaginator
    {
        $query = DocumentgroupName::query();

        // Поиск по названию
        if (!empty($params['search'])) {
            $query->where('name', 'LIKE', "%{$params['search']}%");
        }

        // Фильтр по типу группы
        if (!empty($params['type'])) {
            switch ($params['type']) {
                case 'web':
                    $query->where('private_webgroup', 1);
                    break;
                case 'manager':
                    $query->where('private_memgroup', 1);
                    break;
            }
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?DocumentgroupName
    {
        return DocumentgroupName::find($id);
    }

    public function create(array $data): DocumentgroupName
    {
        $group = DocumentgroupName::create([
            'name' => $data['name'],
            'private_memgroup' => $data['private_memgroup'] ?? 0,
            'private_webgroup' => $data['private_webgroup'] ?? 0,
        ]);

        // Вызов события создания группы документов
        $this->invokeEvent('OnCreateDocGroup', ['group' => $group]);

        return $group;
    }

    public function update(int $id, array $data): ?DocumentgroupName
    {
        $group = $this->findById($id);
        if (!$group) {
            return null;
        }

        $group->update($data);
        return $group->fresh();
    }

    public function delete(int $id): bool
    {
        $group = $this->findById($id);
        if (!$group) {
            return false;
        }

        // Проверка на связанные документы
        if ($group->documents->count() > 0) {
            throw new \Exception('Cannot delete document group with associated documents');
        }

        return $group->delete();
    }

    public function getGroupDocuments(int $groupId): array
    {
        $group = $this->findById($groupId);
        if (!$group) {
            return [];
        }

        return $group->documents()
            ->orderBy('pagetitle', 'asc')
            ->get()
            ->all();
    }

    public function attachDocuments(int $groupId, array $documentIds): array
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new \Exception('Document group not found');
        }

        // Получаем текущие документы в группе
        $currentDocumentIds = $group->documents->pluck('id')->toArray();
        
        // Фильтруем только новые документы
        $newDocumentIds = array_diff($documentIds, $currentDocumentIds);
        
        if (empty($newDocumentIds)) {
            return [
                'added_count' => 0,
                'added_documents' => []
            ];
        }

        // Прикрепляем документы к группе
        $group->documents()->attach($newDocumentIds);

        return [
            'added_count' => count($newDocumentIds),
            'added_documents' => $newDocumentIds
        ];
    }

    public function detachDocument(int $groupId, int $documentId): bool
    {
        $group = $this->findById($groupId);
        if (!$group) {
            return false;
        }

        // Проверяем, существует ли документ в группе
        $documentInGroup = $group->documents()->where('document', $documentId)->exists();
        
        if (!$documentInGroup) {
            return false;
        }

        return $group->documents()->detach($documentId) > 0;
    }

    public function syncDocuments(int $groupId, array $documentIds): array
    {
        $group = $this->findById($groupId);
        if (!$group) {
            throw new \Exception('Document group not found');
        }

        $group->documents()->sync($documentIds);

        return [
            'synced_documents' => $documentIds,
            'documents_count' => count($documentIds)
        ];
    }

    public function formatGroup(DocumentgroupName $group, bool $includeCounts = false): array
    {
        $data = [
            'id' => $group->id,
            'name' => $group->name,
            'private_memgroup' => (bool)$group->private_memgroup,
            'private_webgroup' => (bool)$group->private_webgroup,
            'type' => $this->getGroupType($group),
        ];

        if ($includeCounts) {
            $data['documents_count'] = $group->documents->count();
        }

        return $data;
    }

    public function formatDocument(SiteContent $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->pagetitle,
            'alias' => $document->alias,
            'parent' => $document->parent,
            'published' => (bool)$document->published,
            'deleted' => (bool)$document->deleted,
            'created_at' => $this->safeFormatDate($document->createdon),
            'updated_at' => $this->safeFormatDate($document->editedon),
        ];
    }

    protected function getGroupType(DocumentgroupName $group): string
    {
        if ($group->private_memgroup && $group->private_webgroup) {
            return 'mixed';
        } elseif ($group->private_memgroup) {
            return 'manager';
        } elseif ($group->private_webgroup) {
            return 'web';
        } else {
            return 'public';
        }
    }
}