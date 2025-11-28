<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends ApiController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:id,username,createdon,editedon',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string|max:255',
                'include_attributes' => 'nullable|boolean',
                'include_settings' => 'nullable|boolean',
                'include_groups' => 'nullable|boolean',
                'blocked' => 'nullable|boolean',
                'verified' => 'nullable|boolean',
                'role' => 'nullable|integer|min:0',
            ]);

            $paginator = $this->userService->getAll($validated);
            
            $includeAttributes = $request->get('include_attributes', false);
            $includeSettings = $request->get('include_settings', false);
            $includeGroups = $request->get('include_groups', false);
            
            $users = collect($paginator->items())->map(function($user) use ($includeAttributes, $includeSettings, $includeGroups) {
                return $this->userService->formatUser($user, $includeAttributes, $includeSettings, $includeGroups);
            });
            
            return $this->paginated($users, $paginator, 'Users retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch users');
        }
    }

    public function show($id)
    {
        try {
            $user = $this->userService->findById($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }
            
            $formattedUser = $this->userService->formatUser($user, true, true, true, true);
            
            return $this->success($formattedUser, 'User retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:6',
                'email' => 'required|email|unique:user_attributes,email',
                'fullname' => 'required|string|max:255',
                'role' => 'nullable|integer|min:0',
                'blocked' => 'nullable|boolean',
                'verified' => 'nullable|boolean',
                'phone' => 'nullable|string|max:20',
                'mobilephone' => 'nullable|string|max:20',
                'settings' => 'nullable|array',
                'user_groups' => 'nullable|array',
                'user_groups.*' => 'integer|exists:membergroup_names,id',
                'tv_values' => 'nullable|array',
            ]);

            $user = $this->userService->create($validated);
            $formattedUser = $this->userService->formatUser($user, true, true, true, true);
            
            return $this->created($formattedUser, 'User created successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to create user');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $this->userService->findById($id);
                
            if (!$user) {
                return $this->notFound('User not found');
            }

            $validated = $this->validateRequest($request, [
                'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
                'password' => 'nullable|string|min:6',
                'email' => 'sometimes|email|unique:user_attributes,email,' . ($user->attributes->id ?? 0),
                'fullname' => 'sometimes|string|max:255',
                'role' => 'sometimes|integer|min:0',
                'blocked' => 'sometimes|boolean',
                'verified' => 'sometimes|boolean',
                'phone' => 'nullable|string|max:20',
                'mobilephone' => 'nullable|string|max:20',
                'settings' => 'nullable|array',
                'user_groups' => 'nullable|array',
                'user_groups.*' => 'integer|exists:membergroup_names,id',
                'tv_values' => 'nullable|array',
            ]);

            $updatedUser = $this->userService->update($id, $validated);
            $formattedUser = $this->userService->formatUser($updatedUser, true, true, true, true);
            
            return $this->updated($formattedUser, 'User updated successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to update user');
        }
    }

    public function destroy($id)
    {
        try {
            $this->userService->delete($id);

            return $this->deleted('User deleted successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to delete user');
        }
    }

    public function block($id)
    {
        try {
            $user = $this->userService->blockUser($id);
            $formattedUser = $this->userService->formatUser($user, true);
            
            return $this->success($formattedUser, 'User blocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to block user');
        }
    }

    public function unblock($id)
    {
        try {
            $user = $this->userService->unblockUser($id);
            $formattedUser = $this->userService->formatUser($user, true);
            
            return $this->success($formattedUser, 'User unblocked successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to unblock user');
        }
    }

    public function settings($id)
    {
        try {
            $result = $this->userService->getUserSettings($id);

            $settings = $result['settings']->mapWithKeys(function($setting) {
                return [$setting->setting_name => $setting->setting_value];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'settings' => $settings,
            ], 'User settings retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user settings');
        }
    }

    public function groups($id)
    {
        try {
            $result = $this->userService->getUserGroups($id);

            $groups = $result['groups']->map(function($memberGroup) {
                return [
                    'id' => $memberGroup->user_group,
                    'name' => $memberGroup->userGroup->name ?? 'Unknown',
                ];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'groups' => $groups,
            ], 'User groups retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user groups');
        }
    }

    public function tvValues($id)
    {
        try {
            $result = $this->userService->getUserTvValues($id);

            $tvValues = $result['tv_values']->map(function($value) {
                return [
                    'tv_id' => $value->tmplvarid,
                    'tv_name' => $value->tmplvar->name ?? 'Unknown',
                    'tv_caption' => $value->tmplvar->caption ?? '',
                    'value' => $value->value,
                ];
            });

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'tv_values' => $tvValues,
            ], 'User TV values retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user TV values');
        }
    }
}