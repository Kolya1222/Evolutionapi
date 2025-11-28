<?php

namespace roilafx\Evolutionapi\Services\Elements;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteHtmlsnippet;
use Exception;

class ChunkService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SiteHtmlsnippet::query();

        // Поиск по названию или описанию
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
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

        // Фильтр по отключению
        if (isset($params['disabled'])) {
            $query->where('disabled', $params['disabled']);
        }

        // Фильтр по типу кэширования
        if (isset($params['cache_type'])) {
            $query->where('cache_type', $params['cache_type']);
        }

        // Сортировка
        $sortBy = $params['sort_by'] ?? 'name';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $params['per_page'] ?? 20;
        
        return $query->paginate($perPage);
    }

    public function findById(int $id): ?SiteHtmlsnippet
    {
        return SiteHtmlsnippet::with('categories')->find($id);
    }

    public function create(array $data): SiteHtmlsnippet
    {
        // Вызов события перед сохранением
        $this->invokeEvent('OnBeforeChunkFormSave', [
            'mode' => 'new',
            'data' => $data
        ]);

        $chunkData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'snippet' => $data['snippet'],
            'category' => $data['category'] ?? 0,
            'editor_type' => $data['editor_type'] ?? 0,
            'editor_name' => $data['editor_name'] ?? '',
            'cache_type' => $data['cache_type'] ?? false,
            'locked' => $data['locked'] ?? false,
            'disabled' => $data['disabled'] ?? false,
            'createdon' => time(),
            'editedon' => time(),
        ];

        $chunk = SiteHtmlsnippet::create($chunkData);

        // Вызов события после сохранения
        $this->invokeEvent('OnChunkFormSave', [
            'mode' => 'new',
            'id' => $chunk->id,
            'chunk' => $chunk
        ]);

        // Логирование действия менеджера
        $this->logManagerAction('chunk_create', $chunk->id, $chunk->name);

        return $chunk;
    }

    public function update(int $id, array $data): SiteHtmlsnippet
    {
        $chunk = $this->findById($id);
        if (!$chunk) {
            throw new Exception('Chunk not found');
        }

        // Вызов события перед сохранением
        $this->invokeEvent('OnBeforeChunkFormSave', [
            'mode' => 'upd',
            'id' => $id,
            'data' => $data
        ]);

        $updateData = [];
        $fields = [
            'name', 'description', 'snippet', 'category', 'editor_type',
            'editor_name', 'cache_type', 'locked', 'disabled'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $updateData['editedon'] = time();

        $chunk->update($updateData);

        // Вызов события после сохранения
        $this->invokeEvent('OnChunkFormSave', [
            'mode' => 'upd',
            'id' => $chunk->id,
            'chunk' => $chunk
        ]);

        $this->logManagerAction('chunk_save', $chunk->id, $chunk->name);

        return $chunk->fresh();
    }

    public function delete(int $id): bool
    {
        $chunk = $this->findById($id);
        if (!$chunk) {
            throw new Exception('Chunk not found');
        }

        // Вызов события перед удалением
        $this->invokeEvent('OnBeforeChunkFormDelete', [
            'id' => $chunk->id,
            'chunk' => $chunk
        ]);

        $chunk->delete();

        // Вызов события после удаления
        $this->invokeEvent('OnChunkFormDelete', [
            'id' => $chunk->id,
            'chunk' => $chunk
        ]);

        $this->logManagerAction('chunk_delete', $chunk->id, $chunk->name);

        return true;
    }

    public function duplicate(int $id): SiteHtmlsnippet
    {
        $chunk = $this->findById($id);
        if (!$chunk) {
            throw new Exception('Chunk not found');
        }

        $newChunk = $chunk->replicate();
        $newChunk->name = $chunk->name . ' (Copy)';
        $newChunk->createdon = time();
        $newChunk->editedon = time();
        $newChunk->save();

        $this->logManagerAction('chunk_duplicate', $newChunk->id, $newChunk->name);

        return $newChunk;
    }

    public function toggleStatus(int $id, string $field, bool $value): SiteHtmlsnippet
    {
        $chunk = $this->findById($id);
        if (!$chunk) {
            throw new Exception('Chunk not found');
        }

        $chunk->update([
            $field => $value,
            'editedon' => time(),
        ]);

        $action = $field . '_' . ($value ? 'enable' : 'disable');
        $this->logManagerAction('chunk_' . $action, $chunk->id, $chunk->name);

        return $chunk->fresh();
    }

    public function updateContent(int $id, string $content): SiteHtmlsnippet
    {
        $chunk = $this->findById($id);
        if (!$chunk) {
            throw new Exception('Chunk not found');
        }

        $chunk->update([
            'snippet' => $content,
            'editedon' => time(),
        ]);

        $this->logManagerAction('chunk_update_content', $chunk->id, $chunk->name);

        return $chunk->fresh();
    }

    public function execute(int $id, array $params = []): string
    {
        $chunk = $this->findById($id);
        if (!$chunk) {
            throw new Exception('Chunk not found');
        }

        if ($chunk->disabled) {
            throw new Exception('Chunk is disabled');
        }

        return evolutionCMS()->evalSnippet($chunk->snippet, $params);
    }

    public function formatChunk(SiteHtmlsnippet $chunk, bool $includeCategory = false): array
    {
        $data = [
            'id' => $chunk->id,
            'name' => $chunk->name,
            'description' => $chunk->description,
            'editor_type' => $chunk->editor_type,
            'editor_name' => $chunk->editor_name,
            'cache_type' => (bool)$chunk->cache_type,
            'locked' => (bool)$chunk->locked,
            'disabled' => (bool)$chunk->disabled,
            'created_at' => $this->safeFormatDate($chunk->createdon),
            'updated_at' => $this->safeFormatDate($chunk->editedon),
            'is_locked' => $chunk->isAlreadyEdit,
            'locked_info' => $chunk->alreadyEditInfo,
        ];

        if ($includeCategory && $chunk->categories) {
            $data['category'] = [
                'id' => $chunk->categories->id,
                'name' => $chunk->categories->category,
            ];
        }

        return $data;
    }
}