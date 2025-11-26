<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Templates;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\SiteTmplvarContentvalue;
use EvolutionCMS\Models\SiteTmplvar;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TvValueController extends ApiController
{
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

            $query = SiteTmplvarContentvalue::query();

            // Фильтр по документу
            if ($request->has('content_id')) {
                $query->where('contentid', $validated['content_id']);
            }

            // Фильтр по TV параметру
            if ($request->has('tmplvar_id')) {
                $query->where('tmplvarid', $validated['tmplvar_id']);
            }

            // Сортировка по ID
            $query->orderBy('id', 'asc');

            // Пагинация
            $perPage = $validated['per_page'] ?? 20;
            $paginator = $query->paginate($perPage);
            
            $includeResource = $request->get('include_resource', false);
            $includeTmplvar = $request->get('include_tmplvar', false);
            
            // Форматируем данные
            $values = collect($paginator->items())->map(function($value) use ($includeResource, $includeTmplvar) {
                return $this->formatTvValue($value, $includeResource, $includeTmplvar);
            });
            
            return $this->paginated($values, $paginator, 'TV values retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV values');
        }
    }

    public function show($id)
    {
        try {
            $value = SiteTmplvarContentvalue::with(['resource', 'tmplvar'])->find($id);
                
            if (!$value) {
                return $this->notFound('TV value not found');
            }
            
            $formattedValue = $this->formatTvValue($value, true, true);
            
            return $this->success($formattedValue, 'TV value retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV value');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'contentid' => 'required|integer|exists:site_content,id',
                'value' => 'required|string',
            ]);

            // Проверяем, не существует ли уже значение для этой пары TV-документ
            $existingValue = SiteTmplvarContentvalue::where('tmplvarid', $validated['tmplvarid'])
                ->where('contentid', $validated['contentid'])
                ->first();

            if ($existingValue) {
                return $this->error(
                    'TV value already exists for this document and TV',
                    ['tv_value' => 'A value already exists for this TV and document combination'],
                    422
                );
            }

            $value = SiteTmplvarContentvalue::create([
                'tmplvarid' => $validated['tmplvarid'],
                'contentid' => $validated['contentid'],
                'value' => $validated['value'],
            ]);

            $formattedValue = $this->formatTvValue($value->fresh(), true, true);
            
            return $this->created($formattedValue, 'TV value created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create TV value');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $value = SiteTmplvarContentvalue::find($id);
                
            if (!$value) {
                return $this->notFound('TV value not found');
            }

            $validated = $this->validateRequest($request, [
                'value' => 'required|string',
            ]);

            $value->update([
                'value' => $validated['value'],
            ]);

            $formattedValue = $this->formatTvValue($value->fresh(), true, true);
            
            return $this->updated($formattedValue, 'TV value updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update TV value');
        }
    }

    public function destroy($id)
    {
        try {
            $value = SiteTmplvarContentvalue::find($id);
                
            if (!$value) {
                return $this->notFound('TV value not found');
            }

            $value->delete();

            return $this->deleted('TV value deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV value');
        }
    }

    public function byDocument($documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $values = SiteTmplvarContentvalue::where('contentid', $documentId)
                ->with('tmplvar')
                ->get()
                ->map(function($value) {
                    return $this->formatTvValue($value, false, true);
                });

            return $this->success([
                'document_id' => $document->id,
                'document_title' => $document->pagetitle,
                'tv_values' => $values,
                'values_count' => $values->count(),
            ], 'Document TV values retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch document TV values');
        }
    }

    public function byTmplvar($tmplvarId)
    {
        try {
            $tmplvar = SiteTmplvar::find($tmplvarId);
            if (!$tmplvar) {
                return $this->notFound('TV not found');
            }

            $values = SiteTmplvarContentvalue::where('tmplvarid', $tmplvarId)
                ->with('resource')
                ->get()
                ->map(function($value) {
                    return $this->formatTvValue($value, true, false);
                });

            return $this->success([
                'tmplvar_id' => $tmplvar->id,
                'tmplvar_name' => $tmplvar->name,
                'tmplvar_caption' => $tmplvar->caption,
                'tv_values' => $values,
                'values_count' => $values->count(),
            ], 'TV values for template variable retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch TV values for template variable');
        }
    }

    public function setDocumentTvValue(Request $request, $documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $validated = $this->validateRequest($request, [
                'tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'value' => 'required|string',
            ]);

            // Используем updateOrCreate для создания или обновления значения
            $value = SiteTmplvarContentvalue::updateOrCreate(
                [
                    'contentid' => $documentId,
                    'tmplvarid' => $validated['tmplvarid'],
                ],
                [
                    'value' => $validated['value'],
                ]
            );

            $formattedValue = $this->formatTvValue($value->fresh(), false, true);

            return $this->success($formattedValue, 'TV value set successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to set TV value');
        }
    }

    public function setMultipleDocumentTvValues(Request $request, $documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $validated = $this->validateRequest($request, [
                'tv_values' => 'required|array',
                'tv_values.*.tmplvarid' => 'required|integer|exists:site_tmplvars,id',
                'tv_values.*.value' => 'required|string',
            ]);

            $results = [];
            $updatedCount = 0;
            $createdCount = 0;

            foreach ($validated['tv_values'] as $tvValue) {
                $value = SiteTmplvarContentvalue::updateOrCreate(
                    [
                        'contentid' => $documentId,
                        'tmplvarid' => $tvValue['tmplvarid'],
                    ],
                    [
                        'value' => $tvValue['value'],
                    ]
                );

                if ($value->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }

                $results[] = $this->formatTvValue($value, false, true);
            }

            return $this->success([
                'document_id' => $document->id,
                'document_title' => $document->pagetitle,
                'tv_values' => $results,
                'summary' => [
                    'total_processed' => count($results),
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                ],
            ], 'Multiple TV values set successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to set multiple TV values');
        }
    }

    public function deleteDocumentTvValue($documentId, $tmplvarId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $value = SiteTmplvarContentvalue::where('contentid', $documentId)
                ->where('tmplvarid', $tmplvarId)
                ->first();

            if (!$value) {
                return $this->notFound('TV value not found for this document');
            }

            $value->delete();

            return $this->deleted('TV value deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV value');
        }
    }

    public function clearDocumentTvValues($documentId)
    {
        try {
            $document = SiteContent::find($documentId);
            if (!$document) {
                return $this->notFound('Document not found');
            }

            $deletedCount = SiteTmplvarContentvalue::where('contentid', $documentId)->delete();

            return $this->success([
                'document_id' => $document->id,
                'document_title' => $document->pagetitle,
                'deleted_count' => $deletedCount,
            ], 'All TV values cleared for document successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to clear TV values for document');
        }
    }

    protected function formatTvValue($value, $includeResource = false, $includeTmplvar = false)
    {
        $data = [
            'id' => $value->id,
            'tmplvarid' => $value->tmplvarid,
            'contentid' => $value->contentid,
            'value' => $value->value,
        ];

        if ($includeResource && $value->resource) {
            $data['resource'] = [
                'id' => $value->resource->id,
                'pagetitle' => $value->resource->pagetitle,
                'alias' => $value->resource->alias,
            ];
        }

        if ($includeTmplvar && $value->tmplvar) {
            $data['tmplvar'] = [
                'id' => $value->tmplvar->id,
                'name' => $value->tmplvar->name,
                'caption' => $value->tmplvar->caption,
                'type' => $value->tmplvar->type,
            ];
        }

        return $data;
    }
}