<?php

namespace roilafx\Evolutionapi\Services\Elements;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\SiteSnippet;
use EvolutionCMS\Models\SiteModule;
use Exception;

class SnippetService extends BaseService
{
    public function getAll(array $params = [])
    {
        $query = SiteSnippet::query();

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

        // Фильтр по наличию модуля
        if (isset($params['has_module'])) {
            if ($params['has_module']) {
                $query->whereNotNull('moduleguid')->where('moduleguid', '!=', '');
            } else {
                $query->whereNull('moduleguid')->orWhere('moduleguid', '');
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

    public function findById(int $id): ?SiteSnippet
    {
        return SiteSnippet::with(['categories', 'module'])->find($id);
    }

    public function create(array $data): SiteSnippet
    {
        // Вызов события перед сохранением
        $this->invokeEvent('OnBeforeSnipFormSave', [
            'mode' => 'new',
            'data' => $data
        ]);

        $snippetData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'snippet' => $data['snippet'],
            'category' => $data['category'] ?? 0,
            'editor_type' => $data['editor_type'] ?? 0,
            'cache_type' => $data['cache_type'] ?? false,
            'locked' => $data['locked'] ?? false,
            'disabled' => $data['disabled'] ?? false,
            'properties' => $data['properties'] ?? '',
            'moduleguid' => $data['module_guid'] ?? '',
            'createdon' => time(),
            'editedon' => time(),
        ];

        $snippet = SiteSnippet::create($snippetData);

        // Вызов события после сохранения
        $this->invokeEvent('OnSnipFormSave', [
            'mode' => 'new',
            'id' => $snippet->id,
            'snippet' => $snippet
        ]);

        // Логирование действия менеджера
        $this->logManagerAction('snippet_create', $snippet->id, $snippet->name);

        return $snippet;
    }

    public function update(int $id, array $data): SiteSnippet
    {
        $snippet = $this->findById($id);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        // Вызов события перед сохранением
        $this->invokeEvent('OnBeforeSnipFormSave', [
            'mode' => 'upd',
            'id' => $id,
            'data' => $data
        ]);

        $updateData = [];
        $fields = [
            'name', 'description', 'snippet', 'category', 'editor_type',
            'cache_type', 'locked', 'disabled', 'properties', 'moduleguid'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $updateData['editedon'] = time();

        $snippet->update($updateData);

        // Вызов события после сохранения
        $this->invokeEvent('OnSnipFormSave', [
            'mode' => 'upd',
            'id' => $snippet->id,
            'snippet' => $snippet
        ]);

        $this->logManagerAction('snippet_save', $snippet->id, $snippet->name);

        return $snippet->fresh();
    }

    public function delete(int $id): bool
    {
        $snippet = $this->findById($id);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        // Вызов события перед удалением
        $this->invokeEvent('OnBeforeSnipFormDelete', [
            'id' => $snippet->id,
            'snippet' => $snippet
        ]);

        $snippet->delete();

        // Вызов события после удаления
        $this->invokeEvent('OnSnipFormDelete', [
            'id' => $snippet->id,
            'snippet' => $snippet
        ]);

        $this->logManagerAction('snippet_delete', $snippet->id, $snippet->name);

        return true;
    }

    public function duplicate(int $id): SiteSnippet
    {
        $snippet = $this->findById($id);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        $newSnippet = $snippet->replicate();
        $newSnippet->name = $snippet->name . ' (Copy)';
        $newSnippet->createdon = time();
        $newSnippet->editedon = time();
        $newSnippet->save();

        $this->logManagerAction('snippet_duplicate', $newSnippet->id, $newSnippet->name);

        return $newSnippet;
    }

    public function toggleStatus(int $id, string $field, bool $value): SiteSnippet
    {
        $snippet = $this->findById($id);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        $snippet->update([
            $field => $value,
            'editedon' => time(),
        ]);

        $action = $field . '_' . ($value ? 'enable' : 'disable');
        $this->logManagerAction('snippet_' . $action, $snippet->id, $snippet->name);

        return $snippet->fresh();
    }

    public function updateContent(int $id, string $content): SiteSnippet
    {
        $snippet = $this->findById($id);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        $snippet->update([
            'snippet' => $content,
            'editedon' => time(),
        ]);

        $this->logManagerAction('snippet_update_content', $snippet->id, $snippet->name);

        return $snippet->fresh();
    }

    public function updateProperties(int $id, string $properties): SiteSnippet
    {
        $snippet = $this->findById($id);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        $snippet->update([
            'properties' => $properties,
            'editedon' => time(),
        ]);

        $this->logManagerAction('snippet_update_properties', $snippet->id, $snippet->name);

        return $snippet->fresh();
    }

    public function execute(int $id, array $params = []): string
    {
        $snippet = $this->findById($id);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        if ($snippet->disabled) {
            throw new Exception('Snippet is disabled');
        }

        return evolutionCMS()->evalSnippet($snippet->snippet, $params);
    }

    public function attachModule(int $snippetId, string $moduleGuid): SiteSnippet
    {
        $snippet = $this->findById($snippetId);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        $module = SiteModule::where('guid', $moduleGuid)->first();
        if (!$module) {
            throw new Exception('Module not found');
        }

        $snippet->update([
            'moduleguid' => $moduleGuid,
            'editedon' => time(),
        ]);

        $this->logManagerAction('snippet_attach_module', $snippet->id, $snippet->name);

        return $snippet->fresh();
    }

    public function detachModule(int $snippetId): SiteSnippet
    {
        $snippet = $this->findById($snippetId);
        if (!$snippet) {
            throw new Exception('Snippet not found');
        }

        $snippet->update([
            'moduleguid' => '',
            'editedon' => time(),
        ]);

        $this->logManagerAction('snippet_detach_module', $snippet->id, $snippet->name);

        return $snippet->fresh();
    }

    public function parseProperties(string $propertiesString): array
    {
        if (empty($propertiesString)) {
            return [];
        }

        $properties = [];
        $lines = explode("\n", $propertiesString);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $properties[trim($key)] = trim($value);
            }
        }
        
        return $properties;
    }

    public function formatSnippet(SiteSnippet $snippet, bool $includeCategory = false, bool $includeModule = false): array
    {
        $data = [
            'id' => $snippet->id,
            'name' => $snippet->name,
            'description' => $snippet->description,
            'editor_type' => $snippet->editor_type,
            'cache_type' => (bool)$snippet->cache_type,
            'locked' => (bool)$snippet->locked,
            'disabled' => (bool)$snippet->disabled,
            'guid' => $snippet->guid,
            'has_module' => $snippet->hasModule,
            'created_at' => $this->safeFormatDate($snippet->createdon),
            'updated_at' => $this->safeFormatDate($snippet->editedon),
            'is_locked' => $snippet->isAlreadyEdit,
            'locked_info' => $snippet->alreadyEditInfo,
        ];

        if ($includeCategory && $snippet->categories) {
            $data['category'] = [
                'id' => $snippet->categories->id,
                'name' => $snippet->categories->category,
            ];
        }

        if ($includeModule && $snippet->module) {
            $data['module'] = [
                'id' => $snippet->module->id,
                'name' => $snippet->module->name,
                'guid' => $snippet->module->guid,
                'disabled' => (bool)$snippet->module->disabled,
            ];
        }

        return $data;
    }
}