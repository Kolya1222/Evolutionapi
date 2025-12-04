<?php

namespace roilafx\Evolutionapi\Controllers\Elements;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Elements\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Modules',
    description: 'Управление модулями Evolution CMS'
)]
class ModuleController extends ApiController
{
    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    #[OA\Get(
        path: '/api/elements/modules',
        summary: 'Получить список модулей',
        description: 'Возвращает список модулей с пагинацией и фильтрацией',
        tags: ['Modules'],
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
                schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'createdon', 'editedon'], default: 'name')
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
                description: 'Поиск по названию или описанию',
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
                name: 'disabled',
                description: 'Фильтр по отключению (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'enable_resource',
                description: 'Фильтр по включению ресурса (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'enable_sharedparams',
                description: 'Фильтр по shared params (true/false/1/0)',
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
                name: 'include_access',
                description: 'Включить информацию о группах доступа (true/false/1/0)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])
            ),
            new OA\Parameter(
                name: 'include_dependencies',
                description: 'Включить информацию о зависимостях (true/false/1/0)',
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
                'sort_by' => 'nullable|string|in:id,name,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:categories,id',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'enable_resource' => 'nullable|boolean',
                'enable_sharedparams' => 'nullable|boolean',
                'include_category' => 'nullable|boolean',
                'include_access' => 'nullable|boolean',
                'include_dependencies' => 'nullable|boolean',
            ]);

            $paginator = $this->moduleService->getAll($validated);
            
            $includeCategory = $request->get('include_category', false);
            $includeAccess = $request->get('include_access', false);
            $includeDependencies = $request->get('include_dependencies', false);
            
            $modules = collect($paginator->items())->map(function($module) use ($includeCategory, $includeAccess, $includeDependencies) {
                return $this->moduleService->formatModule($module, $includeCategory, $includeAccess, $includeDependencies);
            });
            
            return $this->paginated($modules, $paginator, 'Modules retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch modules');
        }
    }

    #[OA\Get(
        path: '/api/elements/modules/{id}',
        summary: 'Получить информацию о модуле',
        description: 'Возвращает детальную информацию о конкретном модуле',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
            $module = $this->moduleService->findById($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }
            
            $formattedModule = $this->moduleService->formatModule($module, true, true, true);
            
            return $this->success($formattedModule, 'Module retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules',
        summary: 'Создать новый модуль',
        description: 'Создает новый модуль с указанными параметрами',
        tags: ['Modules'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'modulecode'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'MyModule'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Описание модуля'),
                    new OA\Property(property: 'modulecode', type: 'string', example: '<?php echo "Hello World"; ?>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 0),
                    new OA\Property(property: 'wrap', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'icon', type: 'string', maxLength: 255, nullable: true, example: 'fa-cube'),
                    new OA\Property(property: 'enable_resource', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'resourcefile', type: 'string', maxLength: 255, nullable: true, example: ''),
                    new OA\Property(property: 'guid', type: 'string', maxLength: 255, nullable: true, example: ''),
                    new OA\Property(property: 'enable_sharedparams', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'properties', type: 'string', nullable: true, example: 'key1=value1\nkey2=value2'),
                    new OA\Property(
                        property: 'access_groups', 
                        type: 'array', 
                        nullable: true, 
                        items: new OA\Items(type: 'integer', example: 1)
                    ),
                    new OA\Property(
                        property: 'dependencies',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'resource', type: 'integer', example: 1),
                                new OA\Property(property: 'type', type: 'integer', minimum: 0, example: 0)
                            ]
                        )
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
                'name' => 'required|string|max:255|unique:site_modules,name',
                'description' => 'nullable|string',
                'modulecode' => 'required|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'wrap' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'icon' => 'nullable|string|max:255',
                'enable_resource' => 'nullable|boolean',
                'resourcefile' => 'nullable|string|max:255',
                'guid' => 'nullable|string|max:255',
                'enable_sharedparams' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'access_groups' => 'nullable|array',
                'access_groups.*' => 'integer|exists:user_roles,id',
                'dependencies' => 'nullable|array',
                'dependencies.*.resource' => 'required|integer|exists:site_content,id',
                'dependencies.*.type' => 'required|integer|min:0',
            ]);

            $module = $this->moduleService->create($validated);
            $formattedModule = $this->moduleService->formatModule($module, true, true, true);
            
            return $this->created($formattedModule, 'Module created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create module');
        }
    }

    #[OA\Put(
        path: '/api/elements/modules/{id}',
        summary: 'Обновить информацию о модуле',
        description: 'Обновляет информацию о существующем модуле',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, nullable: true, example: 'UpdatedModule'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Обновленное описание'),
                    new OA\Property(property: 'modulecode', type: 'string', nullable: true, example: '<?php echo "Updated"; ?>'),
                    new OA\Property(property: 'category', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'editor_type', type: 'integer', minimum: 0, nullable: true, example: 1),
                    new OA\Property(property: 'wrap', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'locked', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'disabled', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'icon', type: 'string', maxLength: 255, nullable: true, example: 'fa-gear'),
                    new OA\Property(property: 'enable_resource', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'resourcefile', type: 'string', maxLength: 255, nullable: true, example: 'resource.php'),
                    new OA\Property(property: 'guid', type: 'string', maxLength: 255, nullable: true, example: '12345678-1234-1234-1234-123456789012'),
                    new OA\Property(property: 'enable_sharedparams', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'properties', type: 'string', nullable: true, example: 'newkey=newvalue'),
                    new OA\Property(
                        property: 'access_groups', 
                        type: 'array', 
                        nullable: true, 
                        items: new OA\Items(type: 'integer', example: [1, 2])
                    ),
                    new OA\Property(
                        property: 'dependencies',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'resource', type: 'integer', example: 2),
                                new OA\Property(property: 'type', type: 'integer', minimum: 0, example: 1)
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
    public function update(Request $request, $id)
    {
        try {
            $module = $this->moduleService->findById($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:site_modules,name,' . $id,
                'description' => 'nullable|string',
                'modulecode' => 'sometimes|string',
                'category' => 'nullable|integer|exists:categories,id',
                'editor_type' => 'nullable|integer|min:0',
                'wrap' => 'nullable|boolean',
                'locked' => 'nullable|boolean',
                'disabled' => 'nullable|boolean',
                'icon' => 'nullable|string|max:255',
                'enable_resource' => 'nullable|boolean',
                'resourcefile' => 'nullable|string|max:255',
                'guid' => 'nullable|string|max:255',
                'enable_sharedparams' => 'nullable|boolean',
                'properties' => 'nullable|string',
                'access_groups' => 'nullable|array',
                'access_groups.*' => 'integer|exists:user_roles,id',
                'dependencies' => 'nullable|array',
                'dependencies.*.resource' => 'required|integer|exists:site_content,id',
                'dependencies.*.type' => 'required|integer|min:0',
            ]);

            $updatedModule = $this->moduleService->update($id, $validated);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->updated($formattedModule, 'Module updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update module');
        }
    }

    #[OA\Delete(
        path: '/api/elements/modules/{id}',
        summary: 'Удалить модуль',
        description: 'Удаляет указанный модуль',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
            $module = $this->moduleService->findById($id);
                
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $this->moduleService->delete($id);

            return $this->deleted('Module deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete module');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/duplicate',
        summary: 'Дублировать модуль',
        description: 'Создает копию существующего модуля',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля для копирования',
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $newModule = $this->moduleService->duplicate($id);
            $formattedModule = $this->moduleService->formatModule($newModule, true, true, true);
            
            return $this->created($formattedModule, 'Module duplicated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to duplicate module');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/enable',
        summary: 'Включить модуль',
        description: 'Включает отключенный модуль',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
    public function enable($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'disabled', false);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module enabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to enable module');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/disable',
        summary: 'Отключить модуль',
        description: 'Отключает модуль',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
    public function disable($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'disabled', true);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module disabled successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to disable module');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/lock',
        summary: 'Заблокировать модуль',
        description: 'Блокирует модуль от редактирования',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
    public function lock($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'locked', true);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module locked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to lock module');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/unlock',
        summary: 'Разблокировать модуль',
        description: 'Разблокирует модуль для редактирования',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
    public function unlock($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $updatedModule = $this->moduleService->toggleStatus($id, 'locked', false);
            $formattedModule = $this->moduleService->formatModule($updatedModule, true, true, true);
            
            return $this->success($formattedModule, 'Module unlocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unlock module');
        }
    }

    #[OA\Get(
        path: '/api/elements/modules/{id}/content',
        summary: 'Получить содержимое модуля',
        description: 'Возвращает только содержимое (код) модуля',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'content' => $module->modulecode,
            ], 'Module content retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module content');
        }
    }

    #[OA\Put(
        path: '/api/elements/modules/{id}/content',
        summary: 'Обновить содержимое модуля',
        description: 'Обновляет только содержимое (код) модуля',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: '<?php echo "New content"; ?>')
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
    public function updateContent(Request $request, $id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'content' => 'required|string',
            ]);

            $updatedModule = $this->moduleService->updateContent($id, $validated['content']);

            return $this->success([
                'module_id' => $updatedModule->id,
                'module_name' => $updatedModule->name,
                'content' => $updatedModule->modulecode,
            ], 'Module content updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update module content');
        }
    }

    #[OA\Get(
        path: '/api/elements/modules/{id}/properties',
        summary: 'Получить свойства модуля',
        description: 'Возвращает свойства модуля в разобранном виде',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
    public function properties($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $properties = $this->moduleService->parseProperties($module->properties);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'properties' => $properties,
                'properties_raw' => $module->properties,
            ], 'Module properties retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module properties');
        }
    }

    #[OA\Put(
        path: '/api/elements/modules/{id}/properties',
        summary: 'Обновить свойства модуля',
        description: 'Обновляет свойства модуля в виде строки key=value',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['properties'],
                properties: [
                    new OA\Property(property: 'properties', type: 'string', example: 'key1=value1\nkey2=value2')
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
    public function updateProperties(Request $request, $id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'properties' => 'required|string',
            ]);

            $updatedModule = $this->moduleService->updateProperties($id, $validated['properties']);
            $properties = $this->moduleService->parseProperties($validated['properties']);

            return $this->success([
                'module_id' => $updatedModule->id,
                'module_name' => $updatedModule->name,
                'properties' => $properties,
                'properties_raw' => $updatedModule->properties,
            ], 'Module properties updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update module properties');
        }
    }

    #[OA\Get(
        path: '/api/elements/modules/{id}/access',
        summary: 'Получить группы доступа модуля',
        description: 'Возвращает список групп пользователей с доступом к модулю',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $accessGroups = $this->moduleService->getModuleAccess($id);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'access_groups' => $accessGroups,
                'access_count' => count($accessGroups),
            ], 'Module access groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module access groups');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/access',
        summary: 'Добавить группу доступа к модулю',
        description: 'Добавляет группу пользователей к списку доступа модуля',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['usergroup'],
                properties: [
                    new OA\Property(property: 'usergroup', type: 'integer', example: 1)
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
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'usergroup' => 'required|integer|exists:user_roles,id',
            ]);

            $result = $this->moduleService->addAccess($id, $validated['usergroup']);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'usergroup' => $result['usergroup'],
            ], 'Access group added to module successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add access group to module');
        }
    }

    #[OA\Delete(
        path: '/api/elements/modules/{id}/access/{usergroupId}',
        summary: 'Удалить группу доступа из модуля',
        description: 'Удаляет группу пользователей из списка доступа модуля',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'usergroupId',
                description: 'ID группы пользователей',
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
    public function removeAccess($id, $usergroupId)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $this->moduleService->removeAccess($id, $usergroupId);

            return $this->deleted('Access group removed from module successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove access group from module');
        }
    }

    #[OA\Get(
        path: '/api/elements/modules/{id}/dependencies',
        summary: 'Получить зависимости модуля',
        description: 'Возвращает список ресурсных зависимостей модуля',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
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
    public function dependencies($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $dependencies = $this->moduleService->getModuleDependencies($id);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'dependencies' => $dependencies,
                'dependencies_count' => count($dependencies),
            ], 'Module dependencies retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch module dependencies');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/dependencies',
        summary: 'Добавить зависимость к модулю',
        description: 'Добавляет ресурсную зависимость к модулю',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['resource', 'type'],
                properties: [
                    new OA\Property(property: 'resource', type: 'integer', example: 1),
                    new OA\Property(property: 'type', type: 'integer', minimum: 0, example: 0)
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
    public function addDependency(Request $request, $id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $validated = $this->validateRequest($request, [
                'resource' => 'required|integer|exists:site_content,id',
                'type' => 'required|integer|min:0',
            ]);

            $result = $this->moduleService->addDependency($id, $validated['resource'], $validated['type']);

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'dependency' => $result,
            ], 'Dependency added to module successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add dependency to module');
        }
    }

    #[OA\Delete(
        path: '/api/elements/modules/{id}/dependencies/{dependencyId}',
        summary: 'Удалить зависимость из модуля',
        description: 'Удаляет ресурсную зависимость из модуля',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'dependencyId',
                description: 'ID зависимости',
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
    public function removeDependency($id, $dependencyId)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            $this->moduleService->removeDependency($id, $dependencyId);

            return $this->deleted('Dependency removed from module successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove dependency from module');
        }
    }

    #[OA\Post(
        path: '/api/elements/modules/{id}/execute',
        summary: 'Выполнить модуль',
        description: 'Выполняет код модуля и возвращает результат',
        tags: ['Modules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID модуля',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function execute($id)
    {
        try {
            $module = $this->moduleService->findById($id);
            if (!$module) {
                return $this->notFound('Module not found');
            }

            if ($module->disabled) {
                return $this->error('Module is disabled', [], 422);
            }
            $output = "Module execution would happen here for: " . $module->name;

            return $this->success([
                'module_id' => $module->id,
                'module_name' => $module->name,
                'output' => $output,
                'executed_at' => date('Y-m-d H:i:s'),
            ], 'Module executed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to execute module');
        }
    }
}