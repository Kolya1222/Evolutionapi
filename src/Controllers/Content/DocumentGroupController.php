<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Content;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\DocumentgroupName;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentGroupController extends ApiController
{
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_counts' => 'nullable|boolean',
                'type' => 'nullable|string|in:all,web,manager',
            ]);

            $query = DocumentgroupName::query();

            // Поиск по названию группы
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where('name', 'LIKE', "%{$searchTerm}%");
            }

            // Фильтр по типу группы
            if ($request->has('type')) {
                switch ($validated['type']) {
                    case 'web':
                        $query->where('private_webgroup', 1);
                        break;
                    case 'manager':
                        $query->where('private_memgroup', 1);
                        break;
                }
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'name';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeCounts = $request->get('include_counts', false);
            
            $groups = collect($paginator->items())->map(function($group) use ($includeCounts) {
                return $this->formatDocumentGroup($group, $includeCounts);
            });
            
            return $this->paginated($groups, $paginator, 'Document groups retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document groups');
        }
    }

    public function show($id)
    {
        try {
            $group = DocumentgroupName::find($id);
                
            if (!$group) {
                return $this->notFound('Document group not found');
            }
            
            $formattedGroup = $this->formatDocumentGroup($group, true);
            
            return $this->success($formattedGroup, 'Document group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document group');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:245|unique:documentgroup_names,name',
                'private_memgroup' => 'nullable|boolean',
                'private_webgroup' => 'nullable|boolean',
            ]);

            $groupData = [
                'name' => $validated['name'],
                'private_memgroup' => $validated['private_memgroup'] ?? false,
                'private_webgroup' => $validated['private_webgroup'] ?? false,
            ];

            $group = DocumentgroupName::create($groupData);
            
            return $this->created($this->formatDocumentGroup($group), 'Document group created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create document group');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $group = DocumentgroupName::find($id);
                
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:245|unique:documentgroup_names,name,' . $id,
                'private_memgroup' => 'sometimes|boolean',
                'private_webgroup' => 'sometimes|boolean',
            ]);

            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['private_memgroup'])) {
                $updateData['private_memgroup'] = $validated['private_memgroup'];
            }
            if (isset($validated['private_webgroup'])) {
                $updateData['private_webgroup'] = $validated['private_webgroup'];
            }

            $group->update($updateData);

            return $this->updated($this->formatDocumentGroup($group->fresh()), 'Document group updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document group');
        }
    }

    public function destroy($id)
    {
        try {
            $group = DocumentgroupName::find($id);
                
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            // Проверяем, есть ли связанные документы
            if ($group->documents->count() > 0) {
                return $this->error(
                    'Cannot delete document group with associated documents', 
                    ['group' => 'Document group contains documents. Remove documents first or use force delete.'],
                    422
                );
            }

            $group->delete();

            return $this->deleted('Document group deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete document group');
        }
    }

    public function documents($id)
    {
        try {
            $group = DocumentgroupName::find($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $documents = $group->documents()
                ->orderBy('pagetitle', 'asc')
                ->get()
                ->map(function($document) {
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
                });
                
            return $this->success([
                'group' => $this->formatDocumentGroup($group),
                'documents' => $documents,
                'documents_count' => $documents->count(),
            ], 'Documents in group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch documents in group');
        }
    }

    public function attachDocuments(Request $request, $id)
    {
        try {
            $group = DocumentgroupName::find($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $validated = $this->validateRequest($request, [
                'document_ids' => 'required|array',
                'document_ids.*' => 'integer|exists:site_content,id',
            ]);

            // Получаем текущие документы в группе
            $currentDocumentIds = $group->documents->pluck('id')->toArray();
            
            // Фильтруем только новые документы
            $newDocumentIds = array_diff($validated['document_ids'], $currentDocumentIds);
            
            if (empty($newDocumentIds)) {
                return $this->warning(
                    $this->formatDocumentGroup($group, true),
                    'No new documents to add',
                    ['documents' => 'All provided documents are already in the group']
                );
            }

            // Прикрепляем документы к группе
            $group->documents()->attach($newDocumentIds);

            return $this->success([
                'group' => $this->formatDocumentGroup($group->fresh(), true),
                'added_count' => count($newDocumentIds),
                'added_documents' => $newDocumentIds,
            ], 'Documents attached to group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to attach documents to group');
        }
    }

    public function detachDocument($id, $documentId)
    {
        try {
            $group = DocumentgroupName::find($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            // Проверяем, существует ли документ в группе
            $documentInGroup = $group->documents()->where('document', $documentId)->exists();
            
            if (!$documentInGroup) {
                return $this->notFound('Document not found in group');
            }

            // Открепляем документ от группы
            $group->documents()->detach($documentId);

            return $this->success([
                'group' => $this->formatDocumentGroup($group->fresh(), true),
                'detached_document_id' => $documentId,
            ], 'Document detached from group successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to detach document from group');
        }
    }

    public function syncDocuments(Request $request, $id)
    {
        try {
            $group = DocumentgroupName::find($id);
            if (!$group) {
                return $this->notFound('Document group not found');
            }

            $validated = $this->validateRequest($request, [
                'document_ids' => 'required|array',
                'document_ids.*' => 'integer|exists:site_content,id',
            ]);

            // Синхронизируем документы (удаляет старые, добавляет новые)
            $group->documents()->sync($validated['document_ids']);

            return $this->success([
                'group' => $this->formatDocumentGroup($group->fresh(), true),
                'synced_documents' => $validated['document_ids'],
                'documents_count' => count($validated['document_ids']),
            ], 'Documents synced with group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to sync documents with group');
        }
    }

    protected function formatDocumentGroup($group, $includeCounts = false)
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

    protected function getGroupType($group)
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