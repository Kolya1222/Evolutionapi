<?php

namespace roilafx\Evolutionapi\Controllers\Templates;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Templates\TvValueService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TvValueController extends ApiController
{
    protected $tvValueService;

    public function __construct(TvValueService $tvValueService)
    {
        $this->tvValueService = $tvValueService;
    }

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

    public function deleteDocumentTvValue($documentId, $tmplvarId)
    {
        try {
            $this->tvValueService->deleteDocumentTvValue($documentId, $tmplvarId);

            return $this->deleted('TV value deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete TV value');
        }
    }

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