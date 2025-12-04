<?php

namespace roilafx\Evolutionapi\Controllers\Templates;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Templates\TvService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'TVs',
    description: 'Управление TV параметрами Evolution CMS'
)]
class TvController extends ApiController
{
    protected $tvService;

    public function __construct(TvService $tvService)
    {
        $this->tvService = $tvService;
    }

    #[OA\Get(
        path: '/api/templates/tvs',
        summary: 'Получить список TV параметров',
        description: 'Возвращает список TV параметров с пагинацией и фильтрацией',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                description: 'Количество элементов на странице (1-100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)
            ),
            new OA\Parameter(
                name: 'sort_by',
                description: 'Поле для сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'caption', 'rank', 'createdon', 'editedon'], default: 'name')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Порядок сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Поиск по названию, caption или описанию',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'category',
                description: 'ID категории для фильтрации',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'locked',
                description: 'Фильтр по блокировке (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Фильтр по типу TV параметра',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_category',
                description: 'Включить информацию о категории (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_templates_count',
                description: 'Включить количество привязанных шаблонов (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_access_count',
                description: 'Включить количество правил доступа (true/false/1/0)',
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
                'sort_by' => 'nullable|string|in:id,name,caption,rank,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'type' => 'nullable|string|max:255',
                'include_category' => 'nullable|boolean',
                'include_templates_count' => 'nullable|boolean',
                'include_access_count' => 'nullable|boolean',
            ]);

            $paginator = $this->tvService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeTemplatesCount = $request->get('include_templates_count', false);
            $includeAccessCount = $request->get('include_access_count', false);
            
            $tvs = collect($paginator->items())->map(function($tv) use ($includeCategory, $includeTemplatesCount, $includeAccessCount) {
                return $this->tvService->formatTv($tv, $includeCategory, $includeTemplatesCount, $includeAccessCount);
            });
            
            return $this->paginated($tvs, $paginator, 'TVs retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TVs');
        }
    }

    #[OA\Get(
        path: '/api/templates/tvs/{id}',
        summary: 'Получить информацию о TV параметре',
        description: 'Возвращает детальную информацию о конкретном TV параметре',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function show($id)
    {
        try {
            $tv = $this->tvService->findById($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }
            
            $formattedTv = $this->tvService->formatTv($tv, true, true, true);
            
            return $this->success($formattedTv, 'TV retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV');
        }
    }

    #[OA\Post(
        path: '/api/templates/tvs',
        summary: 'Создать новый TV параметр',
        description: 'Создает новый TV параметр с указанными свойствами, шаблонами и доступом',
        tags: ['TVs'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'caption', 'type'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'my_tv'),
                    new OA\Property(property: 'caption', type: 'string', maxLength: 255, example: 'My TV Parameter'),
                    new OA\Property(property: 'type', type: 'string', maxLength: 255, example: 'text'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Описание TV параметра'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'elements', type: 'string', nullable: true, example: 'Option 1==value1||Option 2==value2'),
                    new OA\Property(property: 'rank', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'display', type: 'string', maxLength: 255, nullable: true, example: ''),
                    new OA\Property(property: 'display_params', type: 'string', nullable: true, example: ''),
                    new OA\Property(property: 'default_text', type: 'string', nullable: true, example: 'Default value'),
                    new OA\Property(
                        property: 'properties',
                        type: 'object',
                        nullable: true,
                        additionalProperties: true
                    ),
                    new OA\Property(
                        property: 'template_ids',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [1, 2, 3])
                    ),
                    new OA\Property(
                        property: 'document_group_ids',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [1, 2])
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:site_tmplvars,name',
                'caption' => 'required|string|max:255',
                'type' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'locked' => 'nullable|boolean',
                'elements' => 'nullable|string',
                'rank' => 'nullable|integer|min:0',
                'display' => 'nullable|string|max:255',
                'display_params' => 'nullable|string',
                'default_text' => 'nullable|string',
                'properties' => 'nullable|array',
                'template_ids' => 'nullable|array',
                'template_ids.*' => 'integer|exists:site_templates,id',
                'document_group_ids' => 'nullable|array',
                'document_group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $tv = $this->tvService->create($validated);
            $formattedTv = $this->tvService->formatTv($tv, true, true, true);
            
            return $this->created($formattedTv, 'TV created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create TV');
        }
    }

    #[OA\Put(
        path: '/api/templates/tvs/{id}',
        summary: 'Обновить информацию о TV параметре',
        description: 'Обновляет информацию о существующем TV параметре',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true, example: 'updated_tv'),
                    new OA\Property(property: 'caption', type: 'string', maxLength: 255, nullable: true, example: 'Updated TV Parameter'),
                    new OA\Property(property: 'type', type: 'string', maxLength: 255, nullable: true, example: 'textarea'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Обновленное описание'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'elements', type: 'string', nullable: true, example: 'New Option==new_value'),
                    new OA\Property(property: 'rank', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'display', type: 'string', maxLength: 255, nullable: true, example: 'custom_display'),
                    new OA\Property(property: 'display_params', type: 'string', nullable: true, example: 'params=value'),
                    new OA\Property(property: 'default_text', type: 'string', nullable: true, example: 'Updated default'),
                    new OA\Property(
                        property: 'properties',
                        type: 'object',
                        nullable: true,
                        additionalProperties: true
                    ),
                    new OA\Property(
                        property: 'template_ids',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [4, 5, 6])
                    ),
                    new OA\Property(
                        property: 'document_group_ids',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [3, 4])
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            $tv = $this->tvService->findById($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_tmplvars,name,' . $id,
                'caption' => 'sometimes|string|max:255',
                'type' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'locked' => 'nullable|boolean',
                'elements' => 'nullable|string',
                'rank' => 'nullable|integer|min:0',
                'display' => 'nullable|string|max:255',
                'display_params' => 'nullable|string',
                'default_text' => 'nullable|string',
                'properties' => 'nullable|array',
                'template_ids' => 'nullable|array',
                'template_ids.*' => 'integer|exists:site_templates,id',
                'document_group_ids' => 'nullable|array',
                'document_group_ids.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $updatedTv = $this->tvService->update($id, $validated);
            $formattedTv = $this->tvService->formatTv($updatedTv, true, true, true);
            
            return $this->updated($formattedTv, 'TV updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update TV');
        }
    }

    #[OA\Delete(
        path: '/api/templates/tvs/{id}',
        summary: 'Удалить TV параметр',
        description: 'Удаляет указанный TV параметр',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function destroy($id)
    {
        try {
            $tv = $this->tvService->findById($id);
                
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $this->tvService->delete($id);

            return $this->deleted('TV deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV');
        }
    }

    #[OA\Get(
        path: '/api/templates/tvs/{id}/templates',
        summary: 'Получить шаблоны TV параметра',
        description: 'Возвращает список шаблонов, к которым привязан TV параметр',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function templates($id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $templates = $this->tvService->getTvTemplates($id);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'templates' => $templates,
                'templates_count' => count($templates),
            ], 'TV templates retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV templates');
        }
    }

    #[OA\Post(
        path: '/api/templates/tvs/{id}/templates',
        summary: 'Добавить шаблон к TV параметру',
        description: 'Привязывает TV параметр к шаблону с указанным рангом',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['template_id'],
                properties: [
                    new OA\Property(property: 'template_id', type: 'integer', example: 1),
                    new OA\Property(property: 'rank', type: 'integer', minimum: 0, nullable: true, example: 0)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function addTemplate(Request $request, $id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'template_id' => 'required|integer|exists:site_templates,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            $result = $this->tvService->addTemplateToTv(
                $id, 
                $validated['template_id'], 
                $validated['rank'] ?? 0
            );

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'template' => [
                    'id' => $result['template']->id,
                    'name' => $result['template']->templatename,
                    'description' => $result['template']->description,
                    'rank' => $result['rank'],
                ],
            ], 'Template added to TV successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add template to TV');
        }
    }

    #[OA\Delete(
        path: '/api/templates/tvs/{id}/templates/{templateId}',
        summary: 'Удалить шаблон из TV параметра',
        description: 'Отвязывает TV параметр от шаблона',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'templateId',
                description: 'ID шаблона',
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
    public function removeTemplate($id, $templateId)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $this->tvService->removeTemplateFromTv($id, $templateId);

            return $this->deleted('Template removed from TV successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove template from TV');
        }
    }

    #[OA\Get(
        path: '/api/templates/tvs/{id}/access',
        summary: 'Получить доступ к TV параметру',
        description: 'Возвращает правила доступа к TV параметру по группам документов',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function access($id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $access = $this->tvService->getTvAccess($id);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'access' => $access,
                'access_count' => count($access),
            ], 'TV access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV access');
        }
    }

    #[OA\Post(
        path: '/api/templates/tvs/{id}/access',
        summary: 'Добавить доступ к TV параметру',
        description: 'Добавляет правило доступа для группы документов к TV параметру',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['document_group_id'],
                properties: [
                    new OA\Property(property: 'document_group_id', type: 'integer', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function addAccess(Request $request, $id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $validated = $this->validateRequest($request, [
                'document_group_id' => 'required|integer|exists:documentgroup_names,id',
            ]);

            $result = $this->tvService->addAccessToTv($id, $validated['document_group_id']);

            return $this->success([
                'tv_id' => $tv->id,
                'tv_name' => $tv->name,
                'access' => [
                    'id' => $result['access']->id,
                    'document_group_id' => $result['document_group']->id,
                    'document_group_name' => $result['document_group']->name,
                ],
            ], 'Access added to TV successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add access to TV');
        }
    }

    #[OA\Delete(
        path: '/api/templates/tvs/{id}/access/{accessId}',
        summary: 'Удалить доступ из TV параметра',
        description: 'Удаляет правило доступа к TV параметру',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID TV параметра',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'accessId',
                description: 'ID правила доступа',
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
    public function removeAccess($id, $accessId)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $this->tvService->removeAccessFromTv($id, $accessId);

            return $this->deleted('Access removed from TV successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove access from TV');
        }
    }

    #[OA\Post(
        path: '/api/templates/tvs/{id}/duplicate',
        summary: 'Дублировать TV параметр',
        description: 'Создает копию существующего TV параметра',
        tags: ['TVs'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID TV параметра для копирования',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 201, ref: '#/components/responses/201'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function duplicate($id)
    {
        try {
            $tv = $this->tvService->findById($id);
            if (!$tv) {
                return $this->notFound('TV not found');
            }

            $newTv = $this->tvService->duplicate($id);
            $formattedTv = $this->tvService->formatTv($newTv, true, true, true);
            
            return $this->created($formattedTv, 'TV duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate TV');
        }
    }
}