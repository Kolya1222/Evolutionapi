<?php

namespace roilafx\Evolutionapi\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SuccessResponse',
    description: 'Стандартизированный успешный ответ',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', description: 'Основные данные ответа'),
        new OA\Property(property: 'message', type: 'string', example: 'Операция выполнена успешно'),
        new OA\Property(
            property: 'meta',
            properties: [
                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2024-01-01T12:00:00Z'),
                new OA\Property(property: 'version', type: 'string', example: '1.0'),
                new OA\Property(property: 'env', type: 'string', example: 'production'),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Стандартизированный ответ с ошибкой',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Произошла ошибка'),
        new OA\Property(property: 'errors', type: 'object', description: 'Детали ошибок'),
        new OA\Property(
            property: 'meta',
            properties: [
                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2024-01-01T12:00:00Z'),
                new OA\Property(property: 'version', type: 'string', example: '1.0'),
                new OA\Property(property: 'env', type: 'string', example: 'production'),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'Pagination',
    description: 'Метаданные пагинации',
    properties: [
        new OA\Property(property: 'total', type: 'integer', example: 100),
        new OA\Property(property: 'per_page', type: 'integer', example: 10),
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 10),
        new OA\Property(property: 'from', type: 'integer', example: 1),
        new OA\Property(property: 'to', type: 'integer', example: 10),
    ]
)]
#[OA\Schema(
    schema: 'WarningResponse',
    description: 'Ответ с предупреждениями',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', description: 'Основные данные ответа'),
        new OA\Property(property: 'message', type: 'string', example: 'Операция выполнена с предупреждениями'),
        new OA\Property(property: 'warnings', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(
            property: 'meta',
            properties: [
                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2024-01-01T12:00:00Z'),
                new OA\Property(property: 'version', type: 'string', example: '1.0'),
                new OA\Property(property: 'env', type: 'string', example: 'production'),
            ]
        ),
    ]
)]
class ApiController extends Controller
{
    /**
     * Успешный ответ
     */
    #[OA\Response(
        response: 200,
        description: 'Успешный ответ',
        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
    )]
    #[OA\Response(
        response: 201,
        description: 'Ресурс создан',
        content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
    )]
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
    #[OA\Response(
        response: 409,
        description: 'Конфликт (нельзя удалить)',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    /**
     * Ответ с ошибкой
     */
    #[OA\Response(
        response: 400,
        description: 'Неверный запрос',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
        response: 401,
        description: 'Не авторизован',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
        response: 403,
        description: 'Доступ запрещен',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
        response: 404,
        description: 'Ресурс не найден',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
    #[OA\Response(
        response: 422,
        description: 'Ошибка валидации',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: [
                        'email' => ['The email field is required.'],
                        'password' => ['The password must be at least 8 characters.']
                    ]
                ),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Внутренняя ошибка сервера',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
    )]
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
    #[OA\Response(
        response: 200,
        description: 'Успешный ответ с пагинацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'message', type: 'string', example: 'Данные получены успешно'),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                        new OA\Property(property: 'pagination', ref: '#/components/schemas/Pagination'),
                    ]
                ),
            ]
        )
    )]
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
    #[OA\Response(
        response: 201,
        description: 'Ресурс успешно создан',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', description: 'Созданный ресурс'),
                new OA\Property(property: 'message', type: 'string', example: 'Resource created successfully'),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    protected function created($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Ответ "Обновлено"
     */
    #[OA\Response(
        response: 200,
        description: 'Ресурс успешно обновлен',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', description: 'Обновленный ресурс'),
                new OA\Property(property: 'message', type: 'string', example: 'Resource updated successfully'),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    protected function updated($data = null, string $message = 'Resource updated successfully'): JsonResponse
    {
        return $this->success($data, $message, 200);
    }

    /**
     * Ответ "Удалено"
     */
    #[OA\Response(
        response: 200,
        description: 'Ресурс успешно удален',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'null', example: null),
                new OA\Property(property: 'message', type: 'string', example: 'Resource deleted successfully'),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    protected function deleted(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->success(null, $message, 200);
    }

    /**
     * Ответ "Не найдено"
     */
    #[OA\Response(
        response: 404,
        description: 'Ресурс не найден',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Resource not found'),
                new OA\Property(property: 'errors', type: 'object', example: []),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, [], 404);
    }

    /**
     * Ответ "Запрещено"
     */
    #[OA\Response(
        response: 403,
        description: 'Доступ запрещен',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Access denied'),
                new OA\Property(property: 'errors', type: 'object', example: []),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    protected function forbidden(string $message = 'Access denied'): JsonResponse
    {
        return $this->error($message, [], 403);
    }

    /**
     * Ответ "Не авторизован"
     */
    #[OA\Response(
        response: 401,
        description: 'Не авторизован',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Unauthorized'),
                new OA\Property(property: 'errors', type: 'object', example: []),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
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
    #[OA\Response(
        response: 422,
        description: 'Ошибка валидации данных',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    )
                ),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
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
    #[OA\Response(
        response: 500,
        description: 'Внутренняя ошибка сервера',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'An error occurred while processing your request'),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'exception', type: 'string', example: 'Exception'),
                        new OA\Property(property: 'file', type: 'string', example: '/path/to/file.php'),
                        new OA\Property(property: 'line', type: 'integer', example: 123),
                        new OA\Property(property: 'trace', type: 'array', items: new OA\Items(type: 'string')),
                    ]
                ),
                new OA\Property(
                    property: 'meta',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'env', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    protected function exceptionError(\Exception $exception, string $customMessage = null): JsonResponse
    {
        $message = $customMessage ?: 'An error occurred while processing your request';
        
        \Log::error('API Exception: ' . $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        $errors = [];
        
        if (app()->environment('local', 'development', 'testing')) {
            $errors = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        return $this->error($message, $errors, 500);
    }

    /**
     * Ответ с предупреждением
     */
    #[OA\Response(
        response: 200,
        description: 'Успешный ответ с предупреждениями',
        content: new OA\JsonContent(ref: '#/components/schemas/WarningResponse')
    )]
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