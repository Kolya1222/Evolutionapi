<?php

namespace roilafx\Evolutionapi\Controllers\Templates;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Templates\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Templates',
    description: 'Управление шаблонами Evolution CMS'
)]
class TemplateController extends ApiController
{
    protected $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    #[OA\Get(
        path: '/api/templates/templates',
        summary: 'Получить список шаблонов',
        description: 'Возвращает список шаблонов с пагинацией и фильтрацией',
        tags: ['Templates'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'templatename', 'createdon', 'editedon'], default: 'templatename')
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
                description: 'Поиск по названию или описанию шаблона',
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
                name: 'selectable',
                description: 'Фильтр по selectable (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_category',
                description: 'Включить информацию о категории (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_tvs_count',
                description: 'Включить количество TV параметров (true/false/1/0)',
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
                'sort_by' => 'nullable|string|in:id,templatename,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'selectable' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_tvs_count' => 'nullable|boolean',
            ]);

            $paginator = $this->templateService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeTvsCount = $request->get('include_tvs_count', false);
            
            $templates = collect($paginator->items())->map(function($template) use ($includeCategory, $includeTvsCount) {
                return $this->templateService->formatTemplate($template, $includeCategory, $includeTvsCount);
            });
            
            return $this->paginated($templates, $paginator, 'Templates retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch templates');
        }
    }

    #[OA\Get(
        path: '/api/templates/templates/{id}',
        summary: 'Получить информацию о шаблоне',
        description: 'Возвращает детальную информацию о конкретном шаблоне',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function show($id)
    {
        try {
            $template = $this->templateService->findById($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }
            
            $formattedTemplate = $this->templateService->formatTemplate($template, true, true);
            
            return $this->success($formattedTemplate, 'Template retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch template');
        }
    }

    #[OA\Post(
        path: '/api/templates/templates',
        summary: 'Создать новый шаблон',
        description: 'Создает новый шаблон с указанными параметрами и TV параметрами',
        tags: ['Templates'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['templatename', 'content'],
                properties: [
                    new OA\Property(property: 'templatename', type: 'string', maxLength: 255, example: 'MyTemplate'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Описание шаблона'),
                    new OA\Property(property: 'content', type: 'string', example: '<!DOCTYPE html><html>...</html>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'template_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'icon', type: 'string', maxLength: 255, nullable: true, example: 'fa-file-code'),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'selectable', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'templatealias', type: 'string', maxLength: 255, nullable: true, example: 'my_template'),
                    new OA\Property(property: 'templatecontroller', type: 'string', maxLength: 255, nullable: true, example: ''),
                    new OA\Property(
                        property: 'tv_ids',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [1, 2, 3])
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
                'templatename' => 'required|string|max:255|unique:site_templates,templatename',
                'description' => 'nullable|string',
                'content' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'template_type' => 'nullable|integer|min:0',
                'icon' => 'nullable|string|max:255',
                'locked' => 'nullable|boolean',
                'selectable' => 'nullable|boolean',
                'templatealias' => 'nullable|string|max:255',
                'templatecontroller' => 'nullable|string|max:255',
                'tv_ids' => 'nullable|array',
                'tv_ids.*' => 'integer|exists:site_tmplvars,id',
            ]);

            $template = $this->templateService->create($validated);
            $formattedTemplate = $this->templateService->formatTemplate($template, true, true);
            
            return $this->created($formattedTemplate, 'Template created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create template');
        }
    }

    #[OA\Put(
        path: '/api/templates/templates/{id}',
        summary: 'Обновить информацию о шаблоне',
        description: 'Обновляет информацию о существующем шаблоне',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID шаблона',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'templatename', type: 'string', maxLength: 255, nullable: true, example: 'UpdatedTemplate'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Обновленное описание'),
                    new OA\Property(property: 'content', type: 'string', nullable: true, example: '<!DOCTYPE html><html><!-- Updated --></html>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'template_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'icon', type: 'string', maxLength: 255, nullable: true, example: 'fa-edit'),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'selectable', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'templatealias', type: 'string', maxLength: 255, nullable: true, example: 'updated_template'),
                    new OA\Property(property: 'templatecontroller', type: 'string', maxLength: 255, nullable: true, example: 'Controllers\\TemplateController'),
                    new OA\Property(
                        property: 'tv_ids',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'integer', example: [4, 5, 6])
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
            $template = $this->templateService->findById($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $validated = $this->validateRequest($request, [
                'templatename' => 'sometimes|string|max:255|unique:site_templates,templatename,' . $id,
                'description' => 'nullable|string',
                'content' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'template_type' => 'nullable|integer|min:0',
                'icon' => 'nullable|string|max:255',
                'locked' => 'nullable|boolean',
                'selectable' => 'nullable|boolean',
                'templatealias' => 'nullable|string|max:255',
                'templatecontroller' => 'nullable|string|max:255',
                'tv_ids' => 'nullable|array',
                'tv_ids.*' => 'integer|exists:site_tmplvars,id',
            ]);

            $updatedTemplate = $this->templateService->update($id, $validated);
            $formattedTemplate = $this->templateService->formatTemplate($updatedTemplate, true, true);
            
            return $this->updated($formattedTemplate, 'Template updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update template');
        }
    }

    #[OA\Delete(
        path: '/api/templates/templates/{id}',
        summary: 'Удалить шаблон',
        description: 'Удаляет указанный шаблон (нельзя удалить шаблон, используемый документами)',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID шаблона',
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
            $template = $this->templateService->findById($id);
                
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $this->templateService->delete($id);

            return $this->deleted('Template deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete template');
        }
    }

    #[OA\Get(
        path: '/api/templates/templates/{id}/tvs',
        summary: 'Получить TV параметры шаблона',
        description: 'Возвращает список TV параметров, привязанных к шаблону',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function tvs($id)
    {
        try {
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $tvs = $this->templateService->getTemplateTvs($id);

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'tvs' => $tvs,
                'tvs_count' => count($tvs),
            ], 'Template TVs retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch template TVs');
        }
    }

    #[OA\Post(
        path: '/api/templates/templates/{id}/tvs',
        summary: 'Добавить TV параметр к шаблону',
        description: 'Привязывает TV параметр к шаблону с указанным рангом',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID шаблона',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tv_id'],
                properties: [
                    new OA\Property(property: 'tv_id', type: 'integer', example: 1),
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
    public function addTv(Request $request, $id)
    {
        try {
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $validated = $this->validateRequest($request, [
                'tv_id' => 'required|integer|exists:site_tmplvars,id',
                'rank' => 'nullable|integer|min:0',
            ]);

            $result = $this->templateService->addTvToTemplate(
                $id, 
                $validated['tv_id'], 
                $validated['rank'] ?? 0
            );

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'tv' => [
                    'id' => $result['tv']->id,
                    'name' => $result['tv']->name,
                    'caption' => $result['tv']->caption,
                    'type' => $result['tv']->type,
                    'rank' => $result['rank'],
                ],
            ], 'TV added to template successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add TV to template');
        }
    }

    #[OA\Delete(
        path: '/api/templates/templates/{id}/tvs/{tvId}',
        summary: 'Удалить TV параметр из шаблона',
        description: 'Отвязывает TV параметр от шаблона',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID шаблона',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'tvId',
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
    public function removeTv($id, $tvId)
    {
        try {
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $this->templateService->removeTvFromTemplate($id, $tvId);

            return $this->deleted('TV removed from template successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove TV from template');
        }
    }

    #[OA\Post(
        path: '/api/templates/templates/{id}/duplicate',
        summary: 'Дублировать шаблон',
        description: 'Создает копию существующего шаблона',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID шаблона для копирования',
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
            $template = $this->templateService->findById($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            $newTemplate = $this->templateService->duplicate($id);
            $formattedTemplate = $this->templateService->formatTemplate($newTemplate, true, true);
            
            return $this->created($formattedTemplate, 'Template duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate template');
        }
    }

    #[OA\Get(
        path: '/api/templates/templates/{id}/content',
        summary: 'Получить содержимое шаблона',
        description: 'Возвращает только содержимое (код) шаблона',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(
                name: 'id',
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
    public function content($id)
    {
        try {
            $template = $this->templateService->getTemplateContent($id);
            if (!$template) {
                return $this->notFound('Template not found');
            }

            return $this->success([
                'template_id' => $template->id,
                'template_name' => $template->templatename,
                'content' => $template->content,
            ], 'Template content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch template content');
        }
    }
}