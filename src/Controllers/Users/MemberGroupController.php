<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\MemberGroupService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberGroupController extends ApiController
{
    protected $memberGroupService;

    public function __construct(MemberGroupService $memberGroupService)
    {
        $this->memberGroupService = $memberGroupService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_users_count' => 'nullable|boolean',
                'include_document_groups_count' => 'nullable|boolean',
            ]);

            $paginator = $this->memberGroupService->getAll($validated);
            
            $includeUsersCount = $request->get('include_users_count', false);
            $includeDocGroupsCount = $request->get('include_document_groups_count', false);
            
            $groups = collect($paginator->items())->map(function($group) use ($includeUsersCount, $includeDocGroupsCount) {
                return $this->memberGroupService->formatMemberGroup($group, $includeUsersCount, $includeDocGroupsCount);
            });
            
            return $this->paginated($groups, $paginator, 'Member groups retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch member groups');
        }
    }

    public function show($id)
    {
        try {
            $group = $this->memberGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }
            
            $formattedGroup = $this->memberGroupService->formatMemberGroup($group, true, true);
            
            return $this->success($formattedGroup, 'Member group retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch member group');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'name' => 'required|string|max:255|unique:membergroup_names,name',
                'users' => 'nullable|array',
                'users.*' => 'integer|exists:users,id',
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $group = $this->memberGroupService->create($validated);
            $formattedGroup = $this->memberGroupService->formatMemberGroup($group, true, true);
            
            return $this->created($formattedGroup, 'Member group created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create member group');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $group = $this->memberGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $validated = $this->validateRequest($request, [
                'name' => 'sometimes|string|max:255|unique:membergroup_names,name,' . $id,
                'users' => 'nullable|array',
                'users.*' => 'integer|exists:users,id',
                'document_groups' => 'nullable|array',
                'document_groups.*' => 'integer|exists:documentgroup_names,id',
            ]);

            $updatedGroup = $this->memberGroupService->update($id, $validated);
            $formattedGroup = $this->memberGroupService->formatMemberGroup($updatedGroup, true, true);
            
            return $this->updated($formattedGroup, 'Member group updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update member group');
        }
    }

    public function destroy($id)
    {
        try {
            $group = $this->memberGroupService->findById($id);
                
            if (!$group) {
                return $this->notFound('Member group not found');
            }

            $this->memberGroupService->delete($id);

            return $this->deleted('Member group deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete member group');
        }
    }

    public function users($id)
    {
        try {
            $result = $this->memberGroupService->getGroupUsers($id);
            
            $users = collect($result['users'])->map(function($user) {
                return $this->memberGroupService->formatUser($user);
            });

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'users' => $users,
                'users_count' => $users->count(),
            ], 'Group users retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group users');
        }
    }

    public function addUser(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'user_id' => 'required|integer|exists:users,id',
            ]);

            $result = $this->memberGroupService->addUserToGroup($id, $validated['user_id']);

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'user' => $this->memberGroupService->formatUser($result['user']),
            ], 'User added to group successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add user to group');
        }
    }

    public function removeUser($id, $userId)
    {
        try {
            $this->memberGroupService->removeUserFromGroup($id, $userId);

            return $this->deleted('User removed from group successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove user from group');
        }
    }

    public function documentGroups($id)
    {
        try {
            $result = $this->memberGroupService->getGroupDocumentGroups($id);
            
            $documentGroups = collect($result['document_groups'])->map(function($docGroup) {
                return $this->memberGroupService->formatDocumentGroup($docGroup);
            });

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'document_groups' => $documentGroups,
                'document_groups_count' => $documentGroups->count(),
            ], 'Group document access retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch group document access');
        }
    }

    public function addDocumentGroup(Request $request, $id)
    {
        try {
            $validated = $this->validateRequest($request, [
                'document_group_id' => 'required|integer|exists:documentgroup_names,id',
            ]);

            $result = $this->memberGroupService->addDocumentGroupToGroup($id, $validated['document_group_id']);

            return $this->success([
                'group_id' => $result['group']->id,
                'group_name' => $result['group']->name,
                'document_group' => $this->memberGroupService->formatDocumentGroup($result['document_group']),
            ], 'Document group access added successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to add document group access');
        }
    }

    public function removeDocumentGroup($id, $docGroupId)
    {
        try {
            $this->memberGroupService->removeDocumentGroupFromGroup($id, $docGroupId);

            return $this->deleted('Document group access removed successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to remove document group access');
        }
    }

    public function userGroups($userId)
    {
        try {
            $result = $this->memberGroupService->getUserGroups($userId);

            $groups = collect($result['groups'])->map(function($memberGroup) {
                return [
                    'id' => $memberGroup->group->id,
                    'name' => $memberGroup->group->name,
                ];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'groups' => $groups,
                'groups_count' => $groups->count(),
            ], 'User groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user groups');
        }
    }
}