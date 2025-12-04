<?php

namespace roilafx\Evolutionapi\Controllers\Templates;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Templates\TvValueService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'TV Values',
    description: 'Управление значениями TV параметров документов'
)]
class TvValueController extends ApiController
{
    protected $tvValueService;

    public function __construct(TvValueService $tvValueService)
    {
        $this->tvValueService = $tvValueService;
    }

    #[OA\Get(
        path: '/api/templates/tv-values',
        summary: 'Получить значения TV параметров',
        description: 'Возвращает список значений TV параметров с пагинацией и фильтрацией',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                description: 'Количество элементов на странице (1-100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)
            ),
            new OA\Parameter(
                name: 'content_id',
                description: 'Фильтр по ID документа',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'tmplvar_id',
                description: 'Фильтр по ID TV параметра',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'include_resource',
                description: 'Включить информацию о документе (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_tmplvar',
                description: 'Включить информацию о TV параметре (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'content_id' => 'nullable|integer|exists:site_content,id',
                'tmplvar_id' => 'nullable|integer|exists:site_tmplvars,id',
                'include_resource' => 'nullable|boolean',
                'include_tmplvar' => 'nullable|boolean',
            ]);

            $paginator = $this->tvValueService->getAll($validated);
            
            $includeResource = $request->get('include_resource', false);
            $includeTmplvar = $request->get('include_tmplvar', false);
            
            $values = collect($paginator->items())->map(function($value) use ($includeResource, $includeTmplvar) {
                return $this->tvValueService->formatTvValue($value, $includeResource, $includeTmplvar);
            });
            
            return $this->paginated($values, $paginator, 'TV values retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV values');
        }
    }

    #[OA\Get(
        path: '/api/templates/tv-values/{id}',
        summary: 'Получить информацию о значении TV параметра',
        description: 'Возвращает детальную информацию о конкретном значении TV параметра',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID значения TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function show($id)
    {
        try {
            $value = $this->tvValueService->findById($id);
                
            if (!$value) {
                return $this->notFound('TV value not found');
            }
            
            $formattedValue = $this->tvValueService->formatTvValue($value, true, true);
            
            return $this->success($formattedValue, 'TV value retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV value');
        }
    }

    #[OA\Post(
        path: '/api/templates/tv-values',
        summary: 'Создать новое значение TV параметра',
        description: 'Создает новое значение TV параметра для документа',
        tags: ['TV Values'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tmplvarid', 'contentid', 'value'],
                properties: [
                    new OA\Property(property: 'tmplvarid', type: 'integer', example: 1),
                    new OA\Property(property: 'contentid', type: 'integer', example: 1),
                    new OA\Property(property: 'value', type: 'string', example: 'Значение TV параметра')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'contentid' => 'required|integer|exists:site_content,id',
                'value' => 'required|string',
            ]);

            $value = $this->tvValueService->create($validated);
            $formattedValue = $this->tvValueService->formatTvValue($value, true, true);
            
            return $this->created($formattedValue, 'TV value created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create TV value');
        }
    }

    #[OA\Put(
        path: '/api/templates/tv-values/{id}',
        summary: 'Обновить значение TV параметра',
        description: 'Обновляет значение существующего TV параметра документа',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID значения TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['value'],
                properties: [
                    new OA\Property(property: 'value', type: 'string', example: 'Обновленное значение')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $value = $this->tvValueService->findById($id);
                
            if (!$value) {
                return $this->notFound('TV value not found');
            }

            $validated = $this->validateRequest($request, [
                'value' => 'required|string',
            ]);

            $updatedValue = $this->tvValueService->update($id, $validated);
            $formattedValue = $this->tvValueService->formatTvValue($updatedValue, true, true);
            
            return $this->updated($formattedValue, 'TV value updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update TV value');
        }
    }

    #[OA\Delete(
        path: '/api/templates/tv-values/{id}',
        summary: 'Удалить значение TV параметра',
        description: 'Удаляет значение TV параметра документа',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID значения TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function destroy($id)
    {
        try {
            $value = $this->tvValueService->findById($id);
                
            if (!$value) {
                return $this->notFound('TV value not found');
            }

            $this->tvValueService->delete($id);

            return $this->deleted('TV value deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV value');
        }
    }

    #[OA\Get(
        path: '/api/templates/tv-values/document/{documentId}',
        summary: 'Получить значения TV параметров документа',
        description: 'Возвращает все значения TV параметров для указанного документа',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function byDocument($documentId)
    {
        try {
            $result = $this->tvValueService->getByDocument($documentId);
            
            $values = collect($result['values'])->map(function($value) {
                return $this->tvValueService->formatTvValue($value, false, true);
            });

            return $this->success([
                'document_id' => $result['document']->id,
                'document_title' => $result['document']->pagetitle,
                'tv_values' => $values,
                'values_count' => $values->count(),
            ], 'Document TV values retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document TV values');
        }
    }

    #[OA\Get(
        path: '/api/templates/tv-values/tmplvar/{tmplvarId}',
        summary: 'Получить значения TV параметра для всех документов',
        description: 'Возвращает все значения указанного TV параметра для всех документов',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'tmplvarId',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function byTmplvar($tmplvarId)
    {
        try {
            $result = $this->tvValueService->getByTmplvar($tmplvarId);
            
            $values = collect($result['values'])->map(function($value) {
                return $this->tvValueService->formatTvValue($value, true, false);
            });

            return $this->success([
                'tmplvar_id' => $result['tmplvar']->id,
                'tmplvar_name' => $result['tmplvar']->name,
                'tmplvar_caption' => $result['tmplvar']->caption,
                'tv_values' => $values,
                'values_count' => $values->count(),
            ], 'TV values for template variable retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV values for template variable');
        }
    }

    #[OA\Post(
        path: '/api/templates/tv-values/document/{documentId}/set',
        summary: 'Установить значение TV параметра для документа',
        description: 'Создает или обновляет значение TV параметра для указанного документа',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tmplvarid', 'value'],
                properties: [
                    new OA\Property(property: 'tmplvarid', type: 'integer', example: 1),
                    new OA\Property(property: 'value', type: 'string', example: 'Новое значение')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function setDocumentTvValue(Request $request, $documentId)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'value' => 'required|string',
            ]);

            $value = $this->tvValueService->setDocumentTvValue(
                $documentId, 
                $validated['tmplvarid'], 
                $validated['value']
            );

            $formattedValue = $this->tvValueService->formatTvValue($value, false, true);

            return $this->success($formattedValue, 'TV value set successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to set TV value');
        }
    }

    #[OA\Post(
        path: '/api/templates/tv-values/document/{documentId}/set-multiple',
        summary: 'Установить несколько значений TV параметров для документа',
        description: 'Создает или обновляет несколько значений TV параметров для документа одновременно',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tv_values'],
                properties: [
                    new OA\Property(
                        property: 'tv_values',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'tmplvarid', type: 'integer', example: 1),
                                new OA\Property(property: 'value', type: 'string', example: 'Значение 1')
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function setMultipleDocumentTvValues(Request $request, $documentId)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tv_values' => 'required|array',
                'tv_values.*.tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'tv_values.*.value' => 'required|string',
            ]);

            $result = $this->tvValueService->setMultipleDocumentTvValues($documentId, $validated['tv_values']);

            $formattedValues = collect($result['results'])->map(function($value) {
                return $this->tvValueService->formatTvValue($value, false, true);
            });

            return $this->success([
                'document_id' => $documentId,
                'tv_values' => $formattedValues,
                'summary' => $result['summary'],
            ], 'Multiple TV values set successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to set multiple TV values');
        }
    }

    #[OA\Delete(
        path: '/api/templates/tv-values/document/{documentId}/tv/{tmplvarId}',
        summary: 'Удалить значение TV параметра у документа',
        description: 'Удаляет значение указанного TV параметра у документа',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'tmplvarId',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function deleteDocumentTvValue($documentId, $tmplvarId)
    {
        try {
            $this->tvValueService->deleteDocumentTvValue($documentId, $tmplvarId);

            return $this->deleted('TV value deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV value');
        }
    }

    #[OA\Delete(
        path: '/api/templates/tv-values/document/{documentId}/clear',
        summary: 'Очистить все значения TV параметров у документа',
        description: 'Удаляет все значения TV параметров у указанного документа',
        tags: ['TV Values'],
        parameters: [
            new OA\Parameter(
                name: 'documentId',
                description: 'ID документа',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function clearDocumentTvValues($documentId)
    {
        try {
            $deletedCount = $this->tvValueService->clearDocumentTvValues($documentId);

            return $this->success([
                'document_id' => $documentId,
                'deleted_count' => $deletedCount,
            ], 'All TV values cleared for document successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to clear TV values for document');
        }
    }
}