<?php

namespace roilafx\Evolutionapi\Services\Content;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\ClosureTable;
use EvolutionCMS\Models\SiteContent;
use Exception;

class ClosureService extends BaseService
{
    /**
     * Создание связи между документами в closure table
     */
    public function createRelationship(int $ancestorId, int $descendantId): array
    {
        // Проверяем существование документов
        $ancestor = SiteContent::find($ancestorId);
        $descendant = SiteContent::find($descendantId);
        
        if (!$ancestor || !$descendant) {
            throw new Exception('Ancestor or descendant document not found');
        }

        // Проверяем, не создает ли документ связь с самим собой
        if ($ancestorId === $descendantId) {
            throw new Exception('Cannot create relationship with the same document');
        }

        // Проверяем, не существует ли уже такая связь
        $existingRelationship = ClosureTable::where('ancestor', $ancestorId)
            ->where('descendant', $descendantId)
            ->first();

        if ($existingRelationship) {
            throw new Exception('Closure relationship already exists');
        }

        // Создаем связь используя метод модели ClosureTable
        $closureTable = new ClosureTable();
        $closureTable->insertNode($ancestorId, $descendantId);

        // Получаем созданные связи для возврата
        $createdRelationships = ClosureTable::where('ancestor', $ancestorId)
            ->where('descendant', $descendantId)
            ->get();

        // Логируем действие
        $this->logManagerAction('closure_relationship_create', $ancestorId, 
            "Created relationship with descendant {$descendantId}");

        return [
            'ancestor' => [
                'id' => $ancestor->id,
                'pagetitle' => $ancestor->pagetitle,
            ],
            'descendant' => [
                'id' => $descendant->id,
                'pagetitle' => $descendant->pagetitle,
            ],
            'created_relationships' => $createdRelationships->map(function($closure) {
                return $this->formatClosure($closure);
            }),
            'relationships_count' => $createdRelationships->count(),
        ];
    }

    /**
     * Перемещение документа в дереве
     */
    public function moveNode(int $documentId, ?int $newAncestorId): array
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        // Проверяем, не пытаемся ли переместить документ в самого себя
        if ($newAncestorId === $documentId) {
            throw new Exception('Cannot move document to itself');
        }

        // Проверяем существование нового предка (если указан)
        if ($newAncestorId !== null) {
            $newAncestor = SiteContent::find($newAncestorId);
            if (!$newAncestor) {
                throw new Exception('New ancestor document not found');
            }
        }

        // Создаем временную closure запись для использования метода moveNodeTo
        $closure = new ClosureTable();
        $closure->descendant = $documentId;
        
        // Выполняем перемещение
        $closure->moveNodeTo($newAncestorId);

        // Логируем действие
        $action = $newAncestorId ? 'move' : 'make_root';
        $this->logManagerAction('document_' . $action, $documentId, 
            $newAncestorId ? "Moved to ancestor {$newAncestorId}" : "Made root document");

        return [
            'document_id' => $documentId,
            'document_name' => $document->pagetitle,
            'new_ancestor_id' => $newAncestorId,
            'new_ancestor_name' => $newAncestorId ? SiteContent::find($newAncestorId)->pagetitle : null,
            'moved_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Получение предков документа
     */
    public function getAncestors(int $documentId): array
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
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
                'document' => $ancestorDoc ? $this->formatDocumentInfo($ancestorDoc) : null,
            ];
        });

        return [
            'document_id' => $documentId,
            'document_name' => $document->pagetitle,
            'ancestors' => $ancestorsWithInfo,
            'ancestors_count' => $ancestorsWithInfo->count(),
            'path_depth' => $ancestorsWithInfo->max('depth') ?? 0,
        ];
    }

    /**
     * Получение потомков документа
     */
    public function getDescendants(int $documentId, ?int $maxDepth = null, bool $includeSelf = false): array
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        $query = ClosureTable::where('ancestor', $documentId);

        if (!$includeSelf) {
            $query->where('depth', '>', 0);
        }

        if ($maxDepth) {
            $query->where('depth', '<=', $maxDepth);
        }

        $descendants = $query->orderBy('depth', 'asc')
            ->orderBy('descendant', 'asc')
            ->get();

        $descendantsWithInfo = $descendants->map(function($closure) {
            $descendantDoc = SiteContent::find($closure->descendant);
            return [
                'closure_id' => $closure->closure_id,
                'descendant' => $closure->descendant,
                'depth' => $closure->depth,
                'document' => $descendantDoc ? $this->formatDocumentInfo($descendantDoc) : null,
            ];
        });

        return [
            'document_id' => $documentId,
            'document_name' => $document->pagetitle,
            'descendants' => $descendantsWithInfo,
            'descendants_count' => $descendantsWithInfo->count(),
            'max_depth' => $descendantsWithInfo->max('depth') ?? 0,
        ];
    }

    /**
     * Получение полного пути документа (включая сам документ)
     */
    public function getPath(int $documentId): array
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
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
                'document' => $doc ? $this->formatDocumentInfo($doc) : null,
            ];
        });

        $breadcrumb = $pathWithInfo->pluck('document.pagetitle')
            ->filter()
            ->implode(' → ');

        return [
            'document_id' => $documentId,
            'document_name' => $document->pagetitle,
            'path' => $pathWithInfo,
            'path_length' => $pathWithInfo->count(),
            'breadcrumb' => $breadcrumb,
        ];
    }

    /**
     * Получение поддерева документа
     */
    public function getSubtree(int $documentId, ?int $maxDepth = null, bool $includeSelf = false): array
    {
        $document = SiteContent::find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

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
                'document' => $doc ? $this->formatDocumentInfo($doc) : null,
            ];
        });

        return [
            'root_document_id' => $documentId,
            'root_document_name' => $document->pagetitle,
            'subtree' => $subtreeWithInfo,
            'subtree_size' => $subtreeWithInfo->count(),
            'max_depth' => $subtreeWithInfo->max('depth') ?? 0,
        ];
    }

    /**
     * Получение статистики closure table
     */
    public function getStats(): array
    {
        return [
            'total_relationships' => ClosureTable::count(),
            'max_depth' => ClosureTable::max('depth') ?? 0,
            'avg_depth' => round(ClosureTable::avg('depth') ?? 0, 2),
            'root_documents' => ClosureTable::where('depth', 0)
                ->whereColumn('ancestor', 'descendant')
                ->count(),
            'direct_relationships' => ClosureTable::where('depth', 1)->count(),
            'indirect_relationships' => ClosureTable::where('depth', '>', 1)->count(),
            'most_connected_document' => $this->getMostConnectedDocument(),
            'deepest_path' => $this->getDeepestPath(),
            'tree_structure_health' => $this->checkTreeStructureHealth(),
        ];
    }

    /**
     * Получение самого связанного документа
     */
    private function getMostConnectedDocument(): ?array
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

    /**
     * Получение самого глубокого пути
     */
    private function getDeepestPath(): ?array
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
            'path_length' => $result->max_depth + 1,
        ];
    }

    /**
     * Проверка здоровья структуры дерева
     */
    private function checkTreeStructureHealth(): array
    {
        $totalDocuments = SiteContent::count();
        $totalClosureRelationships = ClosureTable::count();
        
        // Каждый документ должен иметь как минимум одну связь (с самим собой)
        $expectedMinRelationships = $totalDocuments;
        
        // Проверяем документы без closure записей
        $documentsWithoutClosure = SiteContent::whereNotIn('id', function($query) {
            $query->select('descendant')->from('site_content_closure');
        })->count();

        // Проверяем циклические связи
        $cyclicRelationships = ClosureTable::where('depth', '>', 0)
            ->whereColumn('ancestor', 'descendant')
            ->count();

        // Проверяем документы без родительских связей (кроме корневых)
        $orphanedDocuments = SiteContent::where('parent', '>', 0)
            ->whereNotIn('id', function($query) {
                $query->select('descendant')
                    ->from('site_content_closure')
                    ->where('depth', 1);
            })
            ->count();

        return [
            'total_documents' => $totalDocuments,
            'total_relationships' => $totalClosureRelationships,
            'expected_min_relationships' => $expectedMinRelationships,
            'documents_without_closure' => $documentsWithoutClosure,
            'cyclic_relationships' => $cyclicRelationships,
            'orphaned_documents' => $orphanedDocuments,
            'health_score' => $this->calculateHealthScore(
                $totalDocuments,
                $documentsWithoutClosure,
                $cyclicRelationships,
                $orphanedDocuments
            ),
        ];
    }

    /**
     * Расчет оценки здоровья дерева
     */
    private function calculateHealthScore($totalDocs, $noClosure, $cyclic, $orphaned): float
    {
        if ($totalDocs === 0) return 100.0;
        
        $score = 100.0;
        
        // Штраф за документы без closure
        $score -= ($noClosure / $totalDocs) * 50;
        
        // Штраф за циклические связи
        $score -= ($cyclic / $totalDocs) * 30;
        
        // Штраф за потерянные документы
        $score -= ($orphaned / $totalDocs) * 20;
        
        return max(0, round($score, 2));
    }

    /**
     * Форматирование информации о документе
     */
    private function formatDocumentInfo(SiteContent $document): array
    {
        return [
            'id' => $document->id,
            'pagetitle' => $document->pagetitle,
            'alias' => $document->alias,
            'published' => (bool)$document->published,
            'deleted' => (bool)$document->deleted,
            'isfolder' => (bool)$document->isfolder,
            'menuindex' => $document->menuindex,
            'template' => $document->template,
            'created_at' => $this->safeFormatDate($document->createdon),
            'updated_at' => $this->safeFormatDate($document->editedon),
        ];
    }

    /**
     * Форматирование closure записи
     */
    private function formatClosure(ClosureTable $closure): array
    {
        $ancestorDoc = SiteContent::find($closure->ancestor);
        $descendantDoc = SiteContent::find($closure->descendant);

        return [
            'closure_id' => $closure->closure_id,
            'ancestor' => $closure->ancestor,
            'descendant' => $closure->descendant,
            'depth' => $closure->depth,
            'ancestor_info' => $ancestorDoc ? $this->formatDocumentInfo($ancestorDoc) : null,
            'descendant_info' => $descendantDoc ? $this->formatDocumentInfo($descendantDoc) : null,
        ];
    }

    /**
     * Поиск общих предков для двух документов
     */
    public function findCommonAncestors(int $doc1Id, int $doc2Id): array
    {
        $doc1 = SiteContent::find($doc1Id);
        $doc2 = SiteContent::find($doc2Id);
        
        if (!$doc1 || !$doc2) {
            throw new Exception('One or both documents not found');
        }

        // Получаем предков первого документа
        $ancestors1 = ClosureTable::where('descendant', $doc1Id)
            ->pluck('ancestor')
            ->toArray();

        // Получаем предков второго документа
        $ancestors2 = ClosureTable::where('descendant', $doc2Id)
            ->pluck('ancestor')
            ->toArray();

        // Находим общих предков
        $commonAncestors = array_intersect($ancestors1, $ancestors2);

        // Получаем информацию об общих предках
        $commonAncestorsInfo = SiteContent::whereIn('id', $commonAncestors)
            ->get()
            ->map(function($doc) use ($doc1Id, $doc2Id) {
                // Находим глубину для каждого документа
                $depth1 = ClosureTable::where('ancestor', $doc->id)
                    ->where('descendant', $doc1Id)
                    ->value('depth');
                
                $depth2 = ClosureTable::where('ancestor', $doc->id)
                    ->where('descendant', $doc2Id)
                    ->value('depth');

                return [
                    'document' => $this->formatDocumentInfo($doc),
                    'depth_to_doc1' => $depth1,
                    'depth_to_doc2' => $depth2,
                    'total_distance' => $depth1 + $depth2,
                ];
            })
            ->sortBy('total_distance')
            ->values();

        return [
            'document1' => $this->formatDocumentInfo($doc1),
            'document2' => $this->formatDocumentInfo($doc2),
            'common_ancestors' => $commonAncestorsInfo,
            'closest_common_ancestor' => $commonAncestorsInfo->first(),
            'common_ancestors_count' => $commonAncestorsInfo->count(),
        ];
    }

    /**
     * Проверка, является ли один документ предком другого
     */
    public function checkAncestry(int $potentialAncestorId, int $potentialDescendantId): array
    {
        $ancestor = SiteContent::find($potentialAncestorId);
        $descendant = SiteContent::find($potentialDescendantId);
        
        if (!$ancestor || !$descendant) {
            throw new Exception('One or both documents not found');
        }

        $relationship = ClosureTable::where('ancestor', $potentialAncestorId)
            ->where('descendant', $potentialDescendantId)
            ->where('depth', '>', 0)
            ->first();

        $isAncestor = $relationship !== null;
        
        return [
            'potential_ancestor' => $this->formatDocumentInfo($ancestor),
            'potential_descendant' => $this->formatDocumentInfo($descendant),
            'is_ancestor' => $isAncestor,
            'relationship_depth' => $isAncestor ? $relationship->depth : null,
            'direct_relationship' => $isAncestor && $relationship->depth === 1,
        ];
    }
}