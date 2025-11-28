<?php

namespace roilafx\Evolutionapi\Services\Templates;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteTmplvarContentvalue;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\SiteContent;
use Exception;

class TvValueService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SiteTmplvarContentvalue::query();

        // Фильтр по документу
        if (!empty($params['content_id'])) {
            $query->where('contentid', $params['content_id']);
        }

        // Фильтр по TV параметру
        if (!empty($params['tmplvar_id'])) {
            $query->where('tmplvarid', $params['tmplvar_id']);
        }

        // Сортировка по ID
        $query->orderBy('id', 'asc');

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?SiteTmplvarContentvalue
    {
        return SiteTmplvarContentvalue::with(['resource', 'tmplvar'])->find($id);
    }

    public function create(array $data): SiteTmplvarContentvalue
    {
        // Проверяем, не существует ли уже значение для этой пары TV-документ
        $existingValue = SiteTmplvarContentvalue::where('tmplvarid', $data['tmplvarid'])
            ->where('contentid', $data['contentid'])
            ->first();

        if ($existingValue) {
            throw new Exception('TV value already exists for this document and TV');
        }

        $value = SiteTmplvarContentvalue::create([
            'tmplvarid' => $data['tmplvarid'],
            'contentid' => $data['contentid'],
            'value' => $data['value'],
        ]);

        // Логирование действия
        $this->logManagerAction('tv_value_create', $value->id, "TV Value for Document {$data['contentid']}");

        return $value->fresh(['resource', 'tmplvar']);
    }

    public function update(int $id, array $data): SiteTmplvarContentvalue
    {
        $value = $this->findById($id);
        if (!$value) {
            throw new Exception('TV value not found');
        }

        $value->update([
            'value' => $data['value'],
        ]);

        // Логирование действия
        $this->logManagerAction('tv_value_save', $value->id, "TV Value for Document {$value->contentid}");

        return $value->fresh(['resource', 'tmplvar']);
    }

    public function delete(int $id): bool
    {
        $value = $this->findById($id);
        if (!$value) {
            throw new Exception('TV value not found');
        }

        // Логирование действия перед удалением
        $this->logManagerAction('tv_value_delete', $value->id, "TV Value for Document {$value->contentid}");

        $value->delete();

        return true;
    }

    public function getByDocument(int $documentId): array
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        $values = SiteTmplvarContentvalue::where('contentid', $documentId)
            ->with('tmplvar')
            ->get();

        return [
            'document' => $document,
            'values' => $values
        ];
    }

    public function getByTmplvar(int $tmplvarId): array
    {
        $tmplvar = SiteTmplvar::find($tmplvarId);
        if (!$tmplvar) {
            throw new Exception('TV not found');
        }

        $values = SiteTmplvarContentvalue::where('tmplvarid', $tmplvarId)
            ->with('resource')
            ->get();

        return [
            'tmplvar' => $tmplvar,
            'values' => $values
        ];
    }

    public function setDocumentTvValue(int $documentId, int $tmplvarId, string $value): SiteTmplvarContentvalue
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        $tmplvar = SiteTmplvar::find($tmplvarId);
        if (!$tmplvar) {
            throw new Exception('TV not found');
        }

        // Используем updateOrCreate для создания или обновления значения
        $tvValue = SiteTmplvarContentvalue::updateOrCreate(
            [
                'contentid' => $documentId,
                'tmplvarid' => $tmplvarId,
            ],
            [
                'value' => $value,
            ]
        );

        $action = $tvValue->wasRecentlyCreated ? 'tv_value_create' : 'tv_value_save';
        $this->logManagerAction($action, $tvValue->id, "TV Value for Document {$documentId}");

        return $tvValue->fresh(['resource', 'tmplvar']);
    }

    public function setMultipleDocumentTvValues(int $documentId, array $tvValues): array
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        $results = [];
        $updatedCount = 0;
        $createdCount = 0;

        foreach ($tvValues as $tvValue) {
            $tmplvar = SiteTmplvar::find($tvValue['tmplvarid']);
            if (!$tmplvar) {
                throw new Exception("TV with ID {$tvValue['tmplvarid']} not found");
            }

            $value = SiteTmplvarContentvalue::updateOrCreate(
                [
                    'contentid' => $documentId,
                    'tmplvarid' => $tvValue['tmplvarid'],
                ],
                [
                    'value' => $tvValue['value'],
                ]
            );

            if ($value->wasRecentlyCreated) {
                $createdCount++;
                $action = 'tv_value_create';
            } else {
                $updatedCount++;
                $action = 'tv_value_save';
            }

            $this->logManagerAction($action, $value->id, "TV Value for Document {$documentId}");

            $results[] = $value;
        }

        return [
            'results' => $results,
            'summary' => [
                'created' => $createdCount,
                'updated' => $updatedCount,
                'total' => count($results),
            ]
        ];
    }

    public function deleteDocumentTvValue(int $documentId, int $tmplvarId): bool
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        $value = SiteTmplvarContentvalue::where('contentid', $documentId)
            ->where('tmplvarid', $tmplvarId)
            ->first();

        if (!$value) {
            throw new Exception('TV value not found for this document');
        }

        $this->logManagerAction('tv_value_delete', $value->id, "TV Value for Document {$documentId}");

        $value->delete();

        return true;
    }

    public function clearDocumentTvValues(int $documentId): int
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        $deletedCount = SiteTmplvarContentvalue::where('contentid', $documentId)->delete();

        // Логируем действие очистки
        $this->logManagerAction('tv_value_clear', $documentId, "Cleared all TV values for Document {$documentId}");

        return $deletedCount;
    }

    public function formatTvValue(SiteTmplvarContentvalue $value, bool $includeResource = false, bool $includeTmplvar = false): array
    {
        $data = [
            'id' => $value->id,
            'tmplvarid' => $value->tmplvarid,
            'contentid' => $value->contentid,
            'value' => $value->value,
        ];

        if ($includeResource && $value->resource) {
            $data['resource'] = [
                'id' => $value->resource->id,
                'pagetitle' => $value->resource->pagetitle,
                'alias' => $value->resource->alias,
                'published' => (bool)$value->resource->published,
                'deleted' => (bool)$value->resource->deleted,
            ];
        }

        if ($includeTmplvar && $value->tmplvar) {
            $data['tmplvar'] = [
                'id' => $value->tmplvar->id,
                'name' => $value->tmplvar->name,
                'caption' => $value->tmplvar->caption,
                'type' => $value->tmplvar->type,
                'description' => $value->tmplvar->description,
            ];
        }

        return $data;
    }
}