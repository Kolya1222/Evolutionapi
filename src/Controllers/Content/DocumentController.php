<?php

namespace roilafx\Evolutionapi\Controllers\Content;

use roilafx\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use roilafx\Evolutionapi\Services\Content\DocumentService;

class DocumentController extends ApiController
{
    private $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

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
                'group_id' => 'nullable|integer|exists:documentgroup_names,id',
                'group_ids' => 'nullable|array',
                'group_ids.*' => 'integer|exists:documentgroup_names,id',
                'tv' => 'nullable|array',
                'tv_filter' => 'nullable|string',
                'tv_order' => 'nullable|string',
            ]);
            
            $filters = array_merge($validated, [
                'without_protected' => true,
            ]);
            
            $result = $this->documentService->searchDocuments($filters);
            
            $includeTV = $request->get('include_tv', false);
            
            $documents = $result->map(function($document) use ($includeTV) {
                return $this->formatDocument($document, $includeTV);
            });
            
            return $this->paginated($documents, $result, 'Documents retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch documents');
        }
    }

    public function show($id)
    {
        try {
            $document = $this->documentService->getDocument($id);
            
            $document->load([
                'templateValues.tmplvar',
                'documentGroups',
                'tpl'
            ]);
            
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
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $document = $this->documentService->createDocument($validated);
            
            if (isset($validated['document_groups'])) {
                $document->documentGroups()->sync($validated['document_groups']);
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
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $document = $this->documentService->updateDocument($id, $validated);
            
            // Синхронизируем группы документов если указаны
            if (isset($validated['document_groups'])) {
                $document->documentGroups()->sync($validated['document_groups']);
            }
            
            $formattedDocument = $this->formatDocument($document, true);
            
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
            $this->documentService->deleteDocument($id);
            return $this->deleted('Document deleted successfully');
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete document');
        }
    }

    public function restore($id)
    {
        try {
            $document = $this->documentService->restoreDocument($id);
            return $this->success($this->formatDocument($document), 'Document restored successfully');
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to restore document');
        }
    }

    public function publishAll()
    {
        try {
            $result = $this->documentService->publishDocuments();
            return $this->success($result, "{$result['published_count']} documents published");
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to publish documents');
        }
    }

    public function unpublishAll()
    {
        try {
            $result = $this->documentService->unpublishDocuments();
            return $this->success($result, "{$result['unpublished_count']} documents unpublished");
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unpublish documents');
        }
    }

    public function updateTree()
    {
        try {
            $result = $this->documentService->updateTree();
            return $this->success($result, 'Document tree updated successfully');
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document tree');
        }
    }

    public function children($id)
    {
        try {
            $children = $this->documentService->getChildren($id);
            
            $formattedChildren = $children->map(function($document) {
                return $this->formatDocument($document, true);
            });
                
            return $this->success($formattedChildren, 'Document children retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document children');
        }
    }

    public function siblings($id)
    {
        try {
            $document = $this->documentService->getDocument($id);
            
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
            $ancestors = $this->documentService->getAncestors($id);
            
            $formattedAncestors = $ancestors->map(function($ancestor) {
                return $this->formatDocument($ancestor, true);
            });
                
            return $this->success($formattedAncestors, 'Document ancestors retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document ancestors');
        }
    }

    public function descendants($id)
    {
        try {
            $descendants = $this->documentService->getDescendants($id);
            
            $formattedDescendants = $descendants->map(function($descendant) {
                return $this->formatDocument($descendant, true);
            });
                
            return $this->success($formattedDescendants, 'Document descendants retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document descendants');
        }
    }

    public function tree($id = null)
    {
        try {
            $query = SiteContent::where('deleted', 0)
                ->where('published', 1);

            if ($id) {
                $document = $this->documentService->getDocument($id);
                $query = $document->descendantsWithSelf();
            } else {
                $query = $query->where(function($q) {
                    $q->whereNull('parent')->orWhere('parent', 0);
                });
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
                    'template' => $document->template,
                    'template_name' => $document->tpl->templatename ?? null,
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
            $validated = $this->validateRequest($request, [
                'new_parent' => 'required|integer|min:0'
            ]);

            $document = $this->documentService->move($id, $validated['new_parent']);
            
            return $this->success($this->formatDocument($document), 'Document moved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move document');
        }
    }

    public function groups($id)
    {
        try {
            $document = $this->documentService->getDocument($id);
            $groups = $document->documentGroups()
                ->orderBy('name', 'asc')
                ->get()
                ->map(function($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'type' => $this->getGroupType($group),
                        'private_memgroup' => (bool)$group->private_memgroup,
                        'private_webgroup' => (bool)$group->private_webgroup,
                    ];
                });
                
            return $this->success([
                'document' => $this->formatDocument($document),
                'groups' => $groups,
                'groups_count' => $groups->count(),
            ], 'Document groups retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document groups');
        }
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

    public function attachToGroups(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'group_ids' => 'required|array',
                'group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $result = $this->documentService->attachToGroups($id, $validated['group_ids']);

            return $this->success($result, 'Document attached to groups successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to attach document to groups');
        }
    }

    public function detachFromGroup($id, $groupId)
    {
        try {
            $result = $this->documentService->detachFromGroup($id, $groupId);
            
            if (!$result) {
                return $this->notFound('Group not found for this document');
            }

            return $this->success([
                'detached_group_id' => $groupId,
            ], 'Document detached from group successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to detach document from group');
        }
    }

    public function syncGroups(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'group_ids' => 'required|array',
                'group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $result = $this->documentService->syncGroups($id, $validated['group_ids']);

            return $this->success($result, 'Document groups synced successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to sync document groups');
        }
    }

    /**
     * Получение TV параметров документа
     */
    public function getTV($id)
    {
        try {
            $tvData = $this->documentService->getDocumentTVFull($id);
            
            return $this->success([
                'document_id' => $id,
                'tv_count' => count($tvData),
                'tv' => $tvData,
            ], 'Document TV parameters retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document TV parameters');
        }
    }

    /**
     * Обновление TV параметров документа
     */
    public function updateTV(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tv' => 'required|array',
            ]);

            $document = $this->documentService->getDocument($id);
            $this->documentService->saveDocumentTV($document, $validated['tv']);

            return $this->success([
                'document_id' => $id,
                'updated_tv_count' => count($validated['tv']),
            ], 'Document TV parameters updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update document TV parameters');
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
            'template_name' => $document->tpl->templatename ?? null,
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
            'created_at' => $this->documentService->safeFormatDate($document->createdon),
            'updated_at' => $this->documentService->safeFormatDate($document->editedon),
            'published_at' => $this->documentService->safeFormatDate($document->publishedon),
            'publish_date' => $this->documentService->safeFormatDate($document->pub_date),
            'unpublish_date' => $this->documentService->safeFormatDate($document->unpub_date),
            'deleted' => (bool)$document->deleted,
            'deleted_at' => $this->documentService->safeFormatDate($document->deletedon),
            'deleted_by' => $document->deletedby,
            'menutitle' => $document->menutitle,
            'hide_from_tree' => (bool)$document->hide_from_tree,
            'privateweb' => (bool)$document->privateweb,
            'privatemgr' => (bool)$document->privatemgr,
            'content_dispo' => (bool)$document->content_dispo,
            'alias_visible' => (bool)$document->alias_visible,
            //'is_locked' => $document->isAlreadyEdit,
            //'locked_info' => $document->alreadyEditInfo,
        ];
        
        if (!$document->relationLoaded('children')) {
            $data['has_children'] = $document->hasChildren();
            $data['children_count'] = $document->countChildren();
        }
        
        if ($withTV) {
            if ($document->relationLoaded('templateValues')) {
                $data['tv'] = $document->templateValues->mapWithKeys(function($tvValue) {
                    $tv = $tvValue->tmplvar;
                    return [
                        $tv->name => [
                            'value' => $tvValue->value,
                            'tv_id' => $tv->id,
                            'caption' => $tv->caption,
                            'type' => $tv->type,
                        ]
                    ];
                })->toArray();
            } else {
                $data['tv'] = $this->documentService->getDocumentTV($document->id);
            }
        }
        
        return $data;
    }
}