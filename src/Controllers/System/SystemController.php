<?php

namespace roilafx\Evolutionapi\Controllers\System;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\System\SystemService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'System Settings',
    description: 'Управление системными настройками Evolution CMS'
)]
class SystemController extends ApiController
{
    protected $systemService;

    public function __construct(SystemService $systemService)
    {
        $this->systemService = $systemService;
    }

    #[OA\Get(
        path: '/api/systems/settings',
        summary: 'Получить список системных настроек',
        description: 'Возвращает список системных настроек с пагинацией и фильтрацией',
        tags: ['System Settings'],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                description: 'Количество элементов на странице (1-100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50)
            ),
            new OA\Parameter(
                name: 'sort_by',
                description: 'Поле для сортировки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['setting_name', 'setting_value'], default: 'setting_name')
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
                description: 'Поиск по названию или значению настройки',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'group',
                description: 'Фильтр по группе настроек (по префиксу названия)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255)
            ),
            new OA\Parameter(
                name: 'include_hidden',
                description: 'Включить скрытые настройки (начинающиеся с _) (true/false/1/0)',
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
                'sort_by' => 'nullable|string|in:setting_name,setting_value',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'group' => 'nullable|string|max:255',
                'include_hidden' => 'nullable|boolean',
            ]);

            $paginator = $this->systemService->getAll($validated);
            
            $settings = collect($paginator->items())->map(function($setting) {
                return $this->systemService->formatSetting($setting);
            });
            
            return $this->paginated($settings, $paginator, 'System settings retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system settings');
        }
    }

    #[OA\Get(
        path: '/api/systems/settings/{name}',
        summary: 'Получить информацию о системной настройке',
        description: 'Возвращает детальную информацию о конкретной системной настройке',
        tags: ['System Settings'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'Название системной настройки',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function show($name)
    {
        try {
            $setting = $this->systemService->findByName($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }
            
            $formattedSetting = $this->systemService->formatSetting($setting);
            
            return $this->success($formattedSetting, 'System setting retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system setting');
        }
    }

    #[OA\Post(
        path: '/api/systems/settings',
        summary: 'Создать новую системную настройку',
        description: 'Создает новую системную настройку',
        tags: ['System Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['setting_name', 'setting_value'],
                properties: [
                    new OA\Property(property: 'setting_name', type: 'string', maxLength: 255, example: 'my_custom_setting'),
                    new OA\Property(property: 'setting_value', type: 'string', example: 'custom value')
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
                'setting_name' => 'required|string|max:255|unique:system_settings,setting_name',
                'setting_value' => 'required|string',
            ]);

            $setting = $this->systemService->create($validated);
            $formattedSetting = $this->systemService->formatSetting($setting);
            
            return $this->created($formattedSetting, 'System setting created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create system setting');
        }
    }

    #[OA\Put(
        path: '/api/systems/settings/{name}',
        summary: 'Обновить системную настройку',
        description: 'Обновляет значение существующей системной настройки',
        tags: ['System Settings'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'Название системной настройки',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['setting_value'],
                properties: [
                    new OA\Property(property: 'setting_value', type: 'string', example: 'updated value')
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
    public function update(Request $request, $name)
    {
        try {
            $setting = $this->systemService->findByName($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }

            $validated = $this->validateRequest($request, [
                'setting_value' => 'required|string',
            ]);

            $updatedSetting = $this->systemService->update($name, $validated);
            $formattedSetting = $this->systemService->formatSetting($updatedSetting);
            
            return $this->updated($formattedSetting, 'System setting updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update system setting');
        }
    }

    #[OA\Delete(
        path: '/api/systems/settings/{name}',
        summary: 'Удалить системную настройку',
        description: 'Удаляет системную настройку (защищенные настройки удалить нельзя)',
        tags: ['System Settings'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'Название системной настройки',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 409, ref: '#/components/responses/409'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function destroy($name)
    {
        try {
            $setting = $this->systemService->findByName($name);
                
            if (!$setting) {
                return $this->notFound('System setting not found');
            }

            $this->systemService->delete($name);

            return $this->deleted('System setting deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete system setting');
        }
    }

    #[OA\Get(
        path: '/api/systems/settings/multiple',
        summary: 'Получить несколько системных настроек',
        description: 'Возвращает значения нескольких системных настроек по их названиям',
        tags: ['System Settings'],
        parameters: [
            new OA\Parameter(
                name: 'settings',
                description: 'Массив названий настроек (через запятую)',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    maxItems: 50,
                    minItems: 1
                )
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function getMultiple(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'settings' => 'required|array|min:1|max:50',
                'settings.*' => 'required|string|max:255',
            ]);

            $settings = $this->systemService->getMultiple($validated['settings']);

            // Добавляем отсутствующие настройки как null
            foreach ($validated['settings'] as $settingName) {
                if (!isset($settings[$settingName])) {
                    $settings[$settingName] = null;
                }
            }

            return $this->success([
                'settings' => $settings,
                'settings_count' => count($settings),
                'found_count' => count(array_filter($settings)),
            ], 'Multiple system settings retrieved successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch multiple system settings');
        }
    }

    #[OA\Put(
        path: '/api/systems/settings/multiple',
        summary: 'Обновить несколько системных настроек',
        description: 'Обновляет или создает несколько системных настроек одновременно',
        tags: ['System Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['settings'],
                properties: [
                    new OA\Property(
                        property: 'settings',
                        type: 'array',
                        maxItems: 50,
                        minItems: 1,
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'site_name'),
                                new OA\Property(property: 'value', type: 'string', example: 'My Updated Site')
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function updateMultiple(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'settings' => 'required|array|min:1|max:50',
                'settings.*.name' => 'required|string|max:255',
                'settings.*.value' => 'required|string',
            ]);

            $results = $this->systemService->updateMultiple($validated['settings']);

            return $this->success([
                'results' => $results,
                'updated_count' => count($results['updated']),
                'created_count' => count($results['created']),
                'error_count' => count($results['errors']),
                'total_processed' => count($validated['settings']),
            ], 'Multiple system settings updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update multiple system settings');
        }
    }

    #[OA\Get(
        path: '/api/systems/settings/groups',
        summary: 'Получить группы системных настроек',
        description: 'Возвращает список групп системных настроек с количеством настроек в каждой',
        tags: ['System Settings'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function groups()
    {
        try {
            $groups = $this->systemService->getGroups();

            return $this->success([
                'groups' => $groups,
                'groups_count' => count($groups),
                'total_settings' => array_sum(array_column($groups, 'settings_count')),
            ], 'System settings groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system settings groups');
        }
    }

    #[OA\Get(
        path: '/api/systems/settings/group/{groupName}',
        summary: 'Получить настройки по группе',
        description: 'Возвращает все настройки, принадлежащие указанной группе',
        tags: ['System Settings'],
        parameters: [
            new OA\Parameter(
                name: 'groupName',
                description: 'Название группы настроек',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function byGroup($groupName)
    {
        try {
            $settings = $this->systemService->getByGroup($groupName);

            if (empty($settings)) {
                return $this->notFound('No settings found for the specified group');
            }

            return $this->success([
                'group' => $groupName,
                'settings' => $settings,
                'settings_count' => count($settings),
            ], 'System settings by group retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch system settings by group');
        }
    }

    #[OA\Post(
        path: '/api/systems/settings/validate',
        summary: 'Валидация системных настроек',
        description: 'Проверяет корректность значений системных настроек',
        tags: ['System Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['settings'],
                properties: [
                    new OA\Property(
                        property: 'settings',
                        type: 'array',
                        maxItems: 50,
                        minItems: 1,
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'site_url'),
                                new OA\Property(property: 'value', type: 'string', example: 'https://example.com')
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
    public function validateSettings(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'settings' => 'required|array|min:1|max:50',
                'settings.*.name' => 'required|string|max:255',
                'settings.*.value' => 'required|string',
            ]);

            $validationResults = $this->systemService->validateSettings($validated['settings']);

            $validCount = count(array_filter($validationResults, function($result) {
                return $result['valid'];
            }));
            $invalidCount = count($validationResults) - $validCount;

            return $this->success([
                'results' => $validationResults,
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'total_checked' => count($validated['settings']),
            ], 'System settings validation completed');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to validate system settings');
        }
    }
}