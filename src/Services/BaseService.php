<?php

namespace roilafx\Evolutionapi\Services;

abstract class BaseService
{
    protected $core;

    public function __construct()
    {
        $this->core = evolutionCMS();
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
    public function safeFormatDate($dateValue): ?string
    {
        if (empty($dateValue)) return null;
        
        // Если это timestamp (число)
        if (is_numeric($dateValue) && $dateValue > 0) {
            return date('Y-m-d H:i:s', $dateValue);
        }
        
        // Если это строка с датой
        if (is_string($dateValue) && !empty(trim($dateValue))) {
            // Проверяем, является ли строка валидной датой в формате Y-m-d H:i:s
            if (preg_match('/^\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2}$/', $dateValue)) {
                return $dateValue; // Уже в правильном формате, возвращаем как есть
            }
            
            // Пробуем преобразовать другие форматы
            $timestamp = strtotime($dateValue);
            if ($timestamp !== false && $timestamp > 0) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        return null;
    }
}