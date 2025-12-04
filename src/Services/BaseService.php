<?php

namespace roilafx\Evolutionapi\Services;

use EvolutionCMS\Core;

abstract class BaseService
{
    /**
     * @var Core
     */
    protected $core;

    public function __construct()
    {
        $this->core = evolutionCMS();
    }

    /**
     * Вызов события Evolution CMS
     * Может возвращать array или bool
     */
    protected function invokeEvent(string $eventName, array $params = [])
    {
        try {
            $result = $this->core->invokeEvent($eventName, $params);
            
            // Проверяем тип возвращаемого значения
            if (is_array($result)) {
                return $result;
            } elseif (is_bool($result)) {
                return $result;
            } else {
                // Если что-то другое, возвращаем как есть
                return $result;
            }
            
        } catch (\Exception $e) {
            \Log::error("Error invoking event {$eventName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Логирование действий
     * Просто пишем в event_log как информационное событие
     */
    protected function logManagerAction(string $action, $itemId = null, $itemName = null): void
    {
        $message = "API Action: {$action}";
        if ($itemId) {
            $message .= " (ID: {$itemId}";
            if ($itemName) {
                $message .= ", Name: {$itemName}";
            }
            $message .= ")";
        }
        
        if (method_exists($this->core, 'logEvent')) {
            $this->core->logEvent(0, 1, $message, 'EvolutionAPI');
        }

        \Log::info("[EvolutionAPI] {$message}");
    }

    /**
     * Безопасное форматирование даты
     */
    public function safeFormatDate($dateValue): ?string
    {
        if (empty($dateValue)) return null;
        
        if (is_numeric($dateValue) && $dateValue > 0) {
            return date('Y-m-d H:i:s', $dateValue);
        }
        
        if (is_string($dateValue) && !empty(trim($dateValue))) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2}$/', $dateValue)) {
                return $dateValue;
            }
            
            $timestamp = strtotime($dateValue);
            if ($timestamp !== false && $timestamp > 0) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        return null;
    }
}