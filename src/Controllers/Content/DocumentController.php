<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Content;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteContent;
use EvolutionCMS\Models\SiteTmplvarContentvalue;
use EvolutionCMS\Models\SiteTmplvar;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentController extends ApiController
{
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'parent' => 'nullable|integer|min:0',
                'template' => 'nullable|integer|min:0',
                'published' => 'nullable|boolean',
                'deleted' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
                'include_tv' => 'nullable|boolean',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|string|in:menuindex,createdon,editedon,pagetitle',
                'sort_order' => 'nullable|string|in:asc,desc',
                'isfolder' => 'nullable|boolean',
                'content_type' => 'nullable|string',
            ]);

            $query = SiteContent::query();

            // Базовые фильтры
            if (!$request->has('deleted') || !$validated['deleted']) {
                $query->where('deleted', 0);
            }

            if ($request->has('parent')) {
                $query->where('parent', $validated['parent']);
            }

            if ($request->has('template')) {
                $query->where('template', $validated['template']);
            }

            if ($request->has('published')) {
                $query->where('published', $validated['published']);
            }

            if ($request->has('isfolder')) {
                $query->where('isfolder', $validated['isfolder']);
            }

            if ($request->has('content_type')) {
                $query->where('contentType', $validated['content_type']);
            }

            // Поиск
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('pagetitle', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('longtitle', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'menuindex';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeTV = $request->get('include_tv', false);
            
            $documents = collect($paginator->items())->map(function($document) use ($includeTV) {
                return $this->formatDocument($document, $includeTV);
            });
            
            return $this->paginated($documents, $paginator, 'Documents retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch documents');
        }
    }
    public function show($id)
    {
        try {
            $document = SiteContent::where('id', $id)->first();
                
            if (!$document) {
                return $this->notFound('Document not found');
            }
            
            $formattedDocument = $this->formatDocument($document, true);
            
            return $this->success($formattedDocument, 'Document retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'pagetitle' => 'required|string|max:255',
                'parent' => 'required|integer|min:0',
                'template' => 'required|integer|min:0',
                'content' => 'nullable|string',
                'alias' => 'nullable|string|max:255|unique:site_content,alias',
                'menuindex' => 'nullable|integer',
                'published' => 'nullable|boolean',
                'isfolder' => 'nullable|boolean',
                'type' => 'nullable|string|in:document,reference',
                'contentType' => 'nullable|string',
                'description' => 'nullable|string|max:255',
                'longtitle' => 'nullable|string|max:255',
                'introtext' => 'nullable|string',
                'richtext' => 'nullable|boolean',
                'searchable' => 'nullable|boolean',
                'cacheable' => 'nullable|boolean',
                'hidemenu' => 'nullable|boolean',
                'tv' => 'nullable|array',
            ]);

            $currentTimestamp = time();

            // Подготовка данных документа
            $documentData = [
                'pagetitle' => $validated['pagetitle'],
                'parent' => $validated['parent'],
                'template' => $validated['template'],
                'content' => $validated['content'] ?? '',
                'alias' => $validated['alias'] ?? '',
                'menuindex' => $validated['menuindex'] ?? 0,
                'published' => $validated['published'] ?? true,
                'isfolder' => $validated['isfolder'] ?? false,
                'type' => $validated['type'] ?? 'document',
                'contentType' => $validated['contentType'] ?? 'text/html',
                'description' => $validated['description'] ?? '',
                'longtitle' => $validated['longtitle'] ?? '',
                'introtext' => $validated['introtext'] ?? '',
                'richtext' => $validated['richtext'] ?? true,
                'searchable' => $validated['searchable'] ?? true,
                'cacheable' => $validated['cacheable'] ?? true,
                'hidemenu' => $validated['hidemenu'] ?? false,
                'createdon' => $currentTimestamp,
                'editedon' => $currentTimestamp,
                'publishedon' => ($validated['published'] ?? true) ? $currentTimestamp : 0,
            ];

            $document = SiteContent::create($documentData);

            // Сохранение TV параметров
            if (isset($validated['tv']) && is_array($validated['tv'])) {
                $this->saveDocumentTV($document->id, $validated['tv']);
            }

            $formattedDocument = $this->formatDocument($document, true);
            
            return $this->created($formattedDocument, 'Document created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create document');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $document = SiteContent::find($id);
                
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $validated = $this->validateRequest($request, [
                'pagetitle' => 'sometimes|string|max:255',
                'parent' => 'sometimes|integer|min:0',
                'template' => 'sometimes|integer|min:0',
                'content' => 'nullable|string',
                'alias' => 'nullable|string|max:255|unique:site_content,alias,' . $id,
                'menuindex' => 'nullable|integer',
                'published' => 'nullable|boolean',
                'isfolder' => 'nullable|boolean',
                'type' => 'nullable|string|in:document,reference',
                'contentType' => 'nullable|string',
                'description' => 'nullable|string|max:255',
                'longtitle' => 'nullable|string|max:255',
                'introtext' => 'nullable|string',
                'richtext' => 'nullable|boolean',
                'searchable' => 'nullable|boolean',
                'cacheable' => 'nullable|boolean',
                'hidemenu' => 'nullable|boolean',
                'tv' => 'nullable|array',
            ]);

            // Обновление документа
            $updateData = [];
            $fields = [
                'pagetitle', 'parent', 'template', 'content', 'alias', 'menuindex', 
                'published', 'isfolder', 'type', 'contentType', 'description', 
                'longtitle', 'introtext', 'richtext', 'searchable', 'cacheable', 'hidemenu'
            ];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }
            
            $updateData['editedon'] = time();
            
            // Обновляем publishedon если статус публикации изменился
            if (isset($validated['published']) && $validated['published'] && !$document->publishedon) {
                $updateData['publishedon'] = time();
            }
            
            $document->update($updateData);

            // Обновление TV параметров
            if (isset($validated['tv']) && is_array($validated['tv'])) {
                $this->saveDocumentTV($document->id, $validated['tv']);
            }

            $formattedDocument = $this->formatDocument($document->fresh(), true);
            
            return $this->updated($formattedDocument, 'Document updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document');
        }
    }

    public function destroy($id)
    {
        try {
            $document = SiteContent::find($id);
                
            if (!$document) {
                return $this->notFound('Document not found');
            }

            // Мягкое удаление
            $document->update([
                'deleted' => 1,
                'deletedon' => time(),
                'editedon' => time(),
            ]);

            return $this->deleted('Document deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete document');
        }
    }

    public function restore($id)
    {
        try {
            $document = SiteContent::where('id', $id)->where('deleted', 1)->first();
                
            if (!$document) {
                return $this->notFound('Document not found or not deleted');
            }

            $document->update([
                'deleted' => 0,
                'deletedon' => 0,
                'editedon' => time(),
            ]);

            return $this->success($this->formatDocument($document), 'Document restored successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to restore document');
        }
    }

    public function children($id)
    {
        try {
            $document = SiteContent::find($id);
            if (!$document) {
                return $this->notFound('Parent document not found');
            }

            $children = $document->children()
                ->where('deleted', 0)
                ->orderBy('menuindex', 'asc')
                ->get()
                ->map(function($document) {
                    return $this->formatDocument($document, true);
                });
                
            return $this->success($children, 'Document children retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document children');
        }
    }

    public function siblings($id)
    {
        try {
            $document = SiteContent::find($id);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $siblings = $document->siblings()
                ->where('deleted', 0)
                ->orderBy('menuindex', 'asc')
                ->get()
                ->map(function($sibling) {
                    return $this->formatDocument($sibling, true);
                });
                
            return $this->success($siblings, 'Document siblings retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document siblings');
        }
    }

    public function ancestors($id)
    {
        try {
            $document = SiteContent::find($id);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $ancestors = $document->getAncestors()
                ->where('deleted', 0)
                ->map(function($ancestor) {
                    return $this->formatDocument($ancestor, true);
                });
                
            return $this->success($ancestors, 'Document ancestors retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document ancestors');
        }
    }

    public function descendants($id)
    {
        try {
            $document = SiteContent::find($id);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $descendants = $document->getDescendants()
                ->where('deleted', 0)
                ->map(function($descendant) {
                    return $this->formatDocument($descendant, true);
                });
                
            return $this->success($descendants, 'Document descendants retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document descendants');
        }
    }

    public function search(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'q' => 'required|string|min:2|max:255',
                'include_tv' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
                'search_fields' => 'nullable|string|in:all,titles,content',
            ]);

            $query = SiteContent::where('deleted', 0);

            $searchTerm = $validated['q'];
            $searchFields = $validated['search_fields'] ?? 'all';
            
            $query->where(function($q) use ($searchTerm, $searchFields) {
                if ($searchFields === 'all' || $searchFields === 'titles') {
                    $q->orWhere('pagetitle', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('longtitle', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('menutitle', 'LIKE', "%{$searchTerm}%");
                }
                
                if ($searchFields === 'all' || $searchFields === 'content') {
                    $q->orWhere('content', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('introtext', 'LIKE', "%{$searchTerm}%");
                }
                
                if ($searchFields === 'all') {
                    $q->orWhere('alias', 'LIKE', "%{$searchTerm}%");
                }
            });

            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeTV = $request->get('include_tv', false);
            
            $documents = $paginator->getCollection()->map(function($document) use ($includeTV) {
                return $this->formatDocument($document, $includeTV);
            });
            
            return $this->paginated($documents, $paginator, 'Search completed successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Search failed');
        }
    }

    public function tree($id = null)
    {
        try {
            $query = SiteContent::where('deleted', 0)
                ->where('published', 1);

            if ($id) {
                $document = SiteContent::find($id);
                if (!$document) {
                    return $this->notFound('Document not found');
                }
                $query = $document->descendantsWithSelf();
            } else {
                $query = $query->whereNull('parent')->orWhere('parent', 0);
            }

            $documents = $query->get()->map(function($document) {
                return [
                    'id' => $document->id,
                    'title' => $document->pagetitle,
                    'alias' => $document->alias,
                    'parent' => $document->parent,
                    'isfolder' => (bool)$document->isfolder,
                    'menuindex' => $document->menuindex,
                    'published' => (bool)$document->published,
                    'has_children' => $document->hasChildren(),
                    'children_count' => $document->countChildren(),
                ];
            });

            return $this->success($documents, 'Document tree retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document tree');
        }
    }

    public function move(Request $request, $id)
    {
        try {
            $document = SiteContent::find($id);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $validated = $this->validateRequest($request, [
                'parent' => 'required|integer|min:0',
                'position' => 'required|integer|min:0',
            ]);

            $document->moveTo($validated['position'], $validated['parent']);

            return $this->success($this->formatDocument($document->fresh()), 'Document moved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move document');
        }
    }

    protected function formatDocument($document, $withTV = false)
    {
        $data = [
            'id' => $document->id,
            'type' => $document->type,
            'content_type' => $document->contentType,
            'title' => $document->pagetitle,
            'long_title' => $document->longtitle,
            'description' => $document->description,
            'alias' => $document->alias,
            'parent' => $document->parent,
            'template' => $document->template,
            'menu_index' => $document->menuindex,
            'published' => (bool)$document->published,
            'isfolder' => (bool)$document->isfolder,
            'content' => $document->content,
            'introtext' => $document->introtext,
            'richtext' => (bool)$document->richtext,
            'searchable' => (bool)$document->searchable,
            'cacheable' => (bool)$document->cacheable,
            'hidemenu' => (bool)$document->hidemenu,
            'created_by' => $document->createdby,
            'edited_by' => $document->editedby,
            'published_by' => $document->publishedby,
            'created_at' => $this->safeFormatDate($document->createdon),
            'updated_at' => $this->safeFormatDate($document->editedon),
            'published_at' => $this->safeFormatDate($document->publishedon),
            'publish_date' => $this->safeFormatDate($document->pub_date),
            'unpublish_date' => $this->safeFormatDate($document->unpub_date),
            'deleted' => (bool)$document->deleted,
            'deleted_at' => $this->safeFormatDate($document->deletedon),
            'deleted_by' => $document->deletedby,
            'menutitle' => $document->menutitle,
            'hide_from_tree' => (bool)$document->hide_from_tree,
            'privateweb' => (bool)$document->privateweb,
            'privatemgr' => (bool)$document->privatemgr,
            'content_dispo' => (bool)$document->content_dispo,
            'alias_visible' => (bool)$document->alias_visible,
            // Древовидные отношения
            'has_children' => $document->hasChildren(),
            'children_count' => $document->countChildren(),
            'has_siblings' => $document->hasSiblings(),
            'siblings_count' => $document->countSiblings(),
            'has_ancestors' => $document->hasAncestors(),
            'ancestors_count' => $document->countAncestors(),
            'has_descendants' => $document->hasDescendants(),
            'descendants_count' => $document->countDescendants(),
        ];
        
        if ($withTV) {
            $data['tv'] = $this->getDocumentTV($document->id);
        }
        
        return $data;
    }

    protected function getDocumentTV($documentId)
    {
        $tvValues = SiteTmplvarContentvalue::where('contentid', $documentId)->get();
        $tvData = [];
        
        foreach ($tvValues as $tvValue) {
            $tv = SiteTmplvar::find($tvValue->tmplvarid);
            if ($tv) {
                $tvData[$tv->name] = [
                    'value' => $this->processTVValue($tvValue->value, $tv->type),
                    'tv_id' => $tv->id,
                    'caption' => $tv->caption,
                    'description' => $tv->description,
                    'type' => $tv->type,
                    'default_value' => $tv->default_text,
                    'elements' => $tv->elements,
                ];
            }
        }
        
        return $tvData;
    }

    protected function saveDocumentTV($documentId, array $tvData)
    {
        foreach ($tvData as $tvName => $tvValue) {
            $tv = SiteTmplvar::where('name', $tvName)->first();
            
            if ($tv) {
                $tvContentValue = SiteTmplvarContentvalue::where('contentid', $documentId)
                    ->where('tmplvarid', $tv->id)
                    ->first();
                
                $processedValue = $this->processTVValueForSave($tvValue, $tv->type);
                
                if ($tvContentValue) {
                    $tvContentValue->update(['value' => $processedValue]);
                } else {
                    SiteTmplvarContentvalue::create([
                        'tmplvarid' => $tv->id,
                        'contentid' => $documentId,
                        'value' => $processedValue,
                    ]);
                }
            }
        }
    }

    protected function processTVValue($value, $type)
    {
        if (empty($value)) {
            return $value;
        }

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

    protected function processTVValueForSave($value, $type)
    {
        if ($type === 'custom_tv:multitv' || is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        if (in_array($type, ['list-multiple', 'checkbox']) && is_array($value)) {
            return implode('||', $value);
        }
        
        return $value;
    }

    protected function parseMultiTV($value)
    {
        if (empty($value)) return [];
        
        $data = json_decode($value, true);
        return $data ?: [];
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

    protected function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}