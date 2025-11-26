<?php

namespace EvolutionCMS\Evolutionapi\Services;

use Exception;

abstract class BaseService
{
    protected $core;

    public function __construct()
    {
        $this->core = evolutionCMS();
    }

    /**
     * Абстрактный метод - должен быть реализован в дочерних классах
     */
    abstract protected function hasPermission(string $permission): bool;

    /**
     * Проверка прав с выбрасыванием исключения
     */
    protected function checkPermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new Exception("Access denied: {$permission} permission required");
        }
    }

    /**
     * Вызов события Evolution CMS
     */
    protected function invokeEvent(string $eventName, array $params = [])
    {
        return $this->core->invokeEvent($eventName, $params);
    }

    /**
     * Логирование действий менеджера
     */
    protected function logManagerAction(string $action, $itemId = null, $itemName = null): void
    {
        $this->core->logManagerAction($action, $itemId, $itemName);
    }

    /**
     * Безопасное форматирование даты
     */
    protected function safeFormatDate($dateValue): ?string
    {
        if (!$dateValue) return null;
        
        if (is_numeric($dateValue) && $dateValue > 0) {
            return date('Y-m-d H:i:s', $dateValue);
        }
        
        return null;
    }
}