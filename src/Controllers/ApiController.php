<?php

namespace EvolutionCMS\Evolutionapi\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    /**
     * Успешный ответ
     */
    protected function success($data = null, string $message = '', int $code = 200, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
                'env' => app()->environment(),
            ], $meta)
        ];

        return response()->json($response, $code);
    }

    /**
     * Ответ с ошибкой
     */
    protected function error(string $message = '', array $errors = [], int $code = 400, array $meta = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
                'env' => app()->environment(),
            ], $meta)
        ];

        return response()->json($response, $code);
    }

    /**
     * Пагинированный ответ
     */
    protected function paginated($data, $paginator, string $message = '', array $meta = []): JsonResponse
    {
        return $this->success($data, $message, 200, array_merge([
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem()
            ]
        ], $meta));
    }

    /**
     * Ответ "Создано"
     */
    protected function created($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Ответ "Обновлено"
     */
    protected function updated($data = null, string $message = 'Resource updated successfully'): JsonResponse
    {
        return $this->success($data, $message, 200);
    }

    /**
     * Ответ "Удалено"
     */
    protected function deleted(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->success(null, $message, 200);
    }

    /**
     * Ответ "Не найдено"
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, [], 404);
    }

    /**
     * Ответ "Запрещено"
     */
    protected function forbidden(string $message = 'Access denied'): JsonResponse
    {
        return $this->error($message, [], 403);
    }

    /**
     * Ответ "Не авторизован"
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, [], 401);
    }

    /**
     * Валидация запроса
     */
    protected function validateRequest(Request $request, array $rules, array $messages = [], array $customAttributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Обработка исключений валидации
     */
    protected function validationError(ValidationException $exception): JsonResponse
    {
        return $this->error(
            'Validation failed', 
            $exception->errors(), 
            422
        );
    }

    /**
     * Обработка общих исключений
     */
    protected function exceptionError(\Exception $exception, string $customMessage = null): JsonResponse
    {
        $message = $customMessage ?: 'An error occurred while processing your request';
        
        $errors = [];
        
        // В development режиме добавляем детальную информацию
        if (app()->environment('local', 'development')) {
            $errors = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        return $this->error($message, $errors, 500);
    }

    /**
     * Проверка прав доступа
     */
    protected function checkPermission($permission): bool
    {
        // Здесь можно интегрировать с системой прав Evolution CMS
        // Пока заглушка - реализуйте по необходимости
        return true;
    }

    /**
     * Ответ с предупреждением
     */
    protected function warning($data = null, string $message = '', array $warnings = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'warnings' => $warnings,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
                'env' => app()->environment(),
            ]
        ], 200);
    }
}