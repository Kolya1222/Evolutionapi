<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Content;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\ClosureTable;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClosureController extends ApiController
{
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:closure_id,ancestor,descendant,depth',
                'sort_order' => 'nullable|string|in:asc,desc',
                'ancestor' => 'nullable|integer|exists:site_content,id',
                'descendant' => 'nullable|integer|exists:site_content,id',
                'depth' => 'nullable|integer|min:0',
                'min_depth' => 'nullable|integer|min:0',
                'max_depth' => 'nullable|integer|min:0',
                'include_ancestor_info' => 'nullable|boolean',
                'include_descendant_info' => 'nullable|boolean',
            ]);

            $query = ClosureTable::query();

            // Фильтр по предку
            if ($request->has('ancestor')) {
                $query->where('ancestor', $validated['ancestor']);
            }

            // Фильтр по потомку
            if ($request->has('descendant')) {
                $query->where('descendant', $validated['descendant']);
            }

            // Фильтр по точной глубине
            if ($request->has('depth')) {
                $query->where('depth', $validated['depth']);
            }

            // Фильтр по минимальной глубине
            if ($request->has('min_depth')) {
                $query->where('depth', '>=', $validated['min_depth']);
            }

            // Фильтр по максимальной глубине
            if ($request->has('max_depth')) {
                $query->where('depth', '<=', $validated['max_depth']);
            }

            // Сортировка
            $sortBy = $validated['sort_by'] ?? 'closure_id';
            $sortOrder = $validated['sort_order'] ?? 'asc';
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $validated['per_page'] ?? 50;
            $paginator = $query->paginate($perPage);
            
            $includeAncestorInfo = $request->get('include_ancestor_info', false);
            $includeDescendantInfo = $request->get('include_descendant_info', false);
            
            // Форматируем данные
            $closures = collect($paginator->items())->map(function($closure) use ($includeAncestorInfo, $includeDescendantInfo) {
                return $this->formatClosure($closure, $includeAncestorInfo, $includeDescendantInfo);
            });
            
            return $this->paginated($closures, $paginator, 'Closure relationships retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch closure relationships');
        }
    }

    public function show($id)
    {
        try {
            $closure = ClosureTable::find($id);
                
            if (!$closure) {
                return $this->notFound('Closure relationship not found');
            }
            
            $formattedClosure = $this->formatClosure($closure, true, true);
            
            return $this->success($formattedClosure, 'Closure relationship retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch closure relationship');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'ancestor' => 'required|integer|exists:site_content,id',
                'descendant' => 'required|integer|exists:site_content,id',
                'depth' => 'nullable|integer|min:0',
            ]);

            // Проверяем, не существует ли уже такая связь
            $existingClosure = ClosureTable::where('ancestor', $validated['ancestor'])
                ->where('descendant', $validated['descendant'])
                ->first();

            if ($existingClosure) {
                return $this->error(
                    'Closure relationship already exists',
                    ['closure' => 'This relationship between ancestor and descendant already exists'],
                    422
                );
            }

            $closureData = [
                'ancestor' => $validated['ancestor'],
                'descendant' => $validated['descendant'],
                'depth' => $validated['depth'] ?? 0,
            ];

            $closure = ClosureTable::create($closureData);

            $formattedClosure = $this->formatClosure($closure, true, true);
            
            return $this->created($formattedClosure, 'Closure relationship created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create closure relationship');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $closure = ClosureTable::find($id);
                
            if (!$closure) {
                return $this->notFound('Closure relationship not found');
            }

            $validated = $this->validateRequest($request, [
                'ancestor' => 'sometimes|integer|exists:site_content,id',
                'descendant' => 'sometimes|integer|exists:site_content,id',
                'depth' => 'nullable|integer|min:0',
            ]);

            $updateData = [];
            $fields = ['ancestor', 'descendant', 'depth'];

            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            // Проверяем уникальность при обновлении
            if (isset($validated['ancestor']) || isset($validated['descendant'])) {
                $ancestor = $validated['ancestor'] ?? $closure->ancestor;
                $descendant = $validated['descendant'] ?? $closure->descendant;
                
                $existingClosure = ClosureTable::where('ancestor', $ancestor)
                    ->where('descendant', $descendant)
                    ->where('closure_id', '!=', $id)
                    ->first();

                if ($existingClosure) {
                    return $this->error(
                        'Closure relationship already exists',
                        ['closure' => 'This relationship between ancestor and descendant already exists'],
                        422
                    );
                }
            }

            $closure->update($updateData);

            $formattedClosure = $this->formatClosure($closure->fresh(), true, true);
            
            return $this->updated($formattedClosure, 'Closure relationship updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update closure relationship');
        }
    }

    public function destroy($id)
    {
        try {
            $closure = ClosureTable::find($id);
                
            if (!$closure) {
                return $this->notFound('Closure relationship not found');
            }

            $closure->delete();

            return $this->deleted('Closure relationship deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete closure relationship');
        }
    }

    public function ancestors($documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $ancestors = ClosureTable::where('descendant', $documentId)
                ->where('depth', '>', 0)
                ->orderBy('depth', 'asc')
                ->get();

            $ancestorsWithInfo = $ancestors->map(function($closure) {
                $ancestorDoc = SiteContent::find($closure->ancestor);
                return [
                    'closure_id' => $closure->closure_id,
                    'ancestor' => $closure->ancestor,
                    'depth' => $closure->depth,
                    'document' => $ancestorDoc ? [
                        'id' => $ancestorDoc->id,
                        'pagetitle' => $ancestorDoc->pagetitle,
                        'alias' => $ancestorDoc->alias,
                        'published' => (bool)$ancestorDoc->published,
                        'deleted' => (bool)$ancestorDoc->deleted,
                    ] : null,
                ];
            });

            return $this->success([
                'document_id' => $documentId,
                'document_name' => $document->pagetitle,
                'ancestors' => $ancestorsWithInfo,
                'ancestors_count' => $ancestorsWithInfo->count(),
                'path_depth' => $ancestorsWithInfo->max('depth') ?? 0,
            ], 'Document ancestors retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document ancestors');
        }
    }

    public function descendants($documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $descendants = ClosureTable::where('ancestor', $documentId)
                ->where('depth', '>', 0)
                ->orderBy('depth', 'asc')
                ->get();

            $descendantsWithInfo = $descendants->map(function($closure) {
                $descendantDoc = SiteContent::find($closure->descendant);
                return [
                    'closure_id' => $closure->closure_id,
                    'descendant' => $closure->descendant,
                    'depth' => $closure->depth,
                    'document' => $descendantDoc ? [
                        'id' => $descendantDoc->id,
                        'pagetitle' => $descendantDoc->pagetitle,
                        'alias' => $descendantDoc->alias,
                        'published' => (bool)$descendantDoc->published,
                        'deleted' => (bool)$descendantDoc->deleted,
                    ] : null,
                ];
            });

            return $this->success([
                'document_id' => $documentId,
                'document_name' => $document->pagetitle,
                'descendants' => $descendantsWithInfo,
                'descendants_count' => $descendantsWithInfo->count(),
                'max_depth' => $descendantsWithInfo->max('depth') ?? 0,
            ], 'Document descendants retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document descendants');
        }
    }

    public function path($documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $path = ClosureTable::where('descendant', $documentId)
                ->orderBy('depth', 'asc')
                ->get();

            $pathWithInfo = $path->map(function($closure) {
                $doc = SiteContent::find($closure->ancestor);
                return [
                    'closure_id' => $closure->closure_id,
                    'document_id' => $closure->ancestor,
                    'depth' => $closure->depth,
                    'document' => $doc ? [
                        'id' => $doc->id,
                        'pagetitle' => $doc->pagetitle,
                        'alias' => $doc->alias,
                        'published' => (bool)$doc->published,
                        'deleted' => (bool)$doc->deleted,
                    ] : null,
                ];
            });

            return $this->success([
                'document_id' => $documentId,
                'document_name' => $document->pagetitle,
                'path' => $pathWithInfo,
                'path_length' => $pathWithInfo->count(),
                'breadcrumb' => $pathWithInfo->pluck('document.pagetitle')->filter()->implode(' → '),
            ], 'Document path retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document path');
        }
    }

    public function subtree($documentId, Request $request)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $validated = $this->validateRequest($request, [
                'max_depth' => 'nullable|integer|min:1',
                'include_self' => 'nullable|boolean',
            ]);

            $maxDepth = $validated['max_depth'] ?? null;
            $includeSelf = $validated['include_self'] ?? false;

            $query = ClosureTable::where('ancestor', $documentId);

            if (!$includeSelf) {
                $query->where('depth', '>', 0);
            }

            if ($maxDepth) {
                $query->where('depth', '<=', $maxDepth);
            }

            $subtree = $query->orderBy('depth', 'asc')
                ->orderBy('descendant', 'asc')
                ->get();

            $subtreeWithInfo = $subtree->map(function($closure) {
                $doc = SiteContent::find($closure->descendant);
                return [
                    'closure_id' => $closure->closure_id,
                    'document_id' => $closure->descendant,
                    'depth' => $closure->depth,
                    'document' => $doc ? [
                        'id' => $doc->id,
                        'pagetitle' => $doc->pagetitle,
                        'alias' => $doc->alias,
                        'published' => (bool)$doc->published,
                        'deleted' => (bool)$doc->deleted,
                    ] : null,
                ];
            });

            return $this->success([
                'root_document_id' => $documentId,
                'root_document_name' => $document->pagetitle,
                'subtree' => $subtreeWithInfo,
                'subtree_size' => $subtreeWithInfo->count(),
                'max_depth' => $subtreeWithInfo->max('depth') ?? 0,
            ], 'Document subtree retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document subtree');
        }
    }

    public function createRelationship(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'ancestor_id' => 'required|integer|exists:site_content,id',
                'descendant_id' => 'required|integer|exists:site_content,id',
            ]);

            $ancestor = SiteContent::find($validated['ancestor_id']);
            $descendant = SiteContent::find($validated['descendant_id']);

            if (!$ancestor || !$descendant) {
                return $this->notFound('Ancestor or descendant document not found');
            }

            // Используем метод модели ClosureTable для создания связи
            $closureTable = new ClosureTable();
            $closureTable->insertNode($validated['ancestor_id'], $validated['descendant_id']);

            // Получаем созданные связи
            $createdRelationships = ClosureTable::where('ancestor', $validated['ancestor_id'])
                ->where('descendant', $validated['descendant_id'])
                ->get()
                ->map(function($closure) {
                    return $this->formatClosure($closure, true, true);
                });

            return $this->success([
                'ancestor' => [
                    'id' => $ancestor->id,
                    'pagetitle' => $ancestor->pagetitle,
                ],
                'descendant' => [
                    'id' => $descendant->id,
                    'pagetitle' => $descendant->pagetitle,
                ],
                'created_relationships' => $createdRelationships,
                'relationships_count' => $createdRelationships->count(),
            ], 'Closure relationship created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create closure relationship');
        }
    }

    public function moveNode(Request $request, $documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $validated = $this->validateRequest($request, [
                'new_ancestor_id' => 'nullable|integer|exists:site_content,id',
            ]);

            $newAncestorId = $validated['new_ancestor_id'] ?? null;

            if ($newAncestorId === $documentId) {
                return $this->error(
                    'Cannot move document to itself',
                    ['document' => 'A document cannot be an ancestor of itself'],
                    422
                );
            }

            // Создаем временную closure запись для использования метода moveNodeTo
            $closure = new ClosureTable();
            $closure->descendant = $documentId;
            
            // Выполняем перемещение
            $closure->moveNodeTo($newAncestorId);

            return $this->success([
                'document_id' => $documentId,
                'document_name' => $document->pagetitle,
                'new_ancestor_id' => $newAncestorId,
                'new_ancestor_name' => $newAncestorId ? SiteContent::find($newAncestorId)->pagetitle : null,
                'moved_at' => date('Y-m-d H:i:s'),
            ], 'Document moved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to move document');
        }
    }

    public function stats()
    {
        try {
            $stats = [
                'total_relationships' => ClosureTable::count(),
                'max_depth' => ClosureTable::max('depth') ?? 0,
                'avg_depth' => round(ClosureTable::avg('depth') ?? 0, 2),
                'root_documents' => ClosureTable::where('depth', 0)->whereColumn('ancestor', 'descendant')->count(),
                'direct_relationships' => ClosureTable::where('depth', 1)->count(),
                'indirect_relationships' => ClosureTable::where('depth', '>', 1)->count(),
                'most_connected_document' => $this->getMostConnectedDocument(),
                'deepest_path' => $this->getDeepestPath(),
            ];

            return $this->success($stats, 'Closure table statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch closure table statistics');
        }
    }

    protected function formatClosure($closure, $includeAncestorInfo = false, $includeDescendantInfo = false)
    {
        $data = [
            'closure_id' => $closure->closure_id,
            'ancestor' => $closure->ancestor,
            'descendant' => $closure->descendant,
            'depth' => $closure->depth,
        ];

        if ($includeAncestorInfo) {
            $ancestorDoc = SiteContent::find($closure->ancestor);
            $data['ancestor_info'] = $ancestorDoc ? [
                'id' => $ancestorDoc->id,
                'pagetitle' => $ancestorDoc->pagetitle,
                'alias' => $ancestorDoc->alias,
                'published' => (bool)$ancestorDoc->published,
            ] : null;
        }

        if ($includeDescendantInfo) {
            $descendantDoc = SiteContent::find($closure->descendant);
            $data['descendant_info'] = $descendantDoc ? [
                'id' => $descendantDoc->id,
                'pagetitle' => $descendantDoc->pagetitle,
                'alias' => $descendantDoc->alias,
                'published' => (bool)$descendantDoc->published,
            ] : null;
        }

        return $data;
    }

    protected function getMostConnectedDocument()
    {
        $result = ClosureTable::select('ancestor as document_id')
            ->selectRaw('COUNT(*) as connection_count')
            ->where('depth', '>', 0)
            ->groupBy('ancestor')
            ->orderBy('connection_count', 'desc')
            ->first();

        if (!$result) {
            return null;
        }

        $document = SiteContent::find($result->document_id);
        
        return [
            'document_id' => $result->document_id,
            'document_name' => $document ? $document->pagetitle : 'Unknown',
            'connection_count' => $result->connection_count,
        ];
    }
    
    protected function getDeepestPath()
    {
        $result = ClosureTable::select('descendant')
            ->selectRaw('MAX(depth) as max_depth')
            ->groupBy('descendant')
            ->orderBy('max_depth', 'desc')
            ->first();

        if (!$result) {
            return null;
        }

        $document = SiteContent::find($result->descendant);
        $path = ClosureTable::where('descendant', $result->descendant)
            ->orderBy('depth', 'asc')
            ->get()
            ->map(function($closure) {
                $doc = SiteContent::find($closure->ancestor);
                return $doc ? $doc->pagetitle : 'Unknown';
            })
            ->implode(' → ');

        return [
            'document_id' => $result->descendant,
            'document_name' => $document ? $document->pagetitle : 'Unknown',
            'depth' => $result->max_depth,
            'path' => $path,
        ];
    }
}