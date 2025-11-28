<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'username' => 'required|string|max:255',
                'password' => 'required|string',
                'remember_me' => 'nullable|boolean',
            ]);

            $result = $this->authService->login($validated);

            return $this->success([
                'user' => $result['user'],
                'tokens' => array_merge($result['tokens'], [
                    'session_id' => $result['session']['session_id'],
                ]),
            ], 'Login successful');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Login failed');
        }
    }

    public function logout(Request $request)
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return $this->error('No token provided', [], 401);
            }

            $this->authService->logout($token);

            return $this->success(null, 'Logout successful');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Logout failed');
        }
    }

    public function refresh(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'refresh_token' => 'required|string',
            ]);

            $tokens = $this->authService->refreshToken($validated['refresh_token']);

            return $this->success($tokens, 'Token refreshed successfully');

        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Token refresh failed');
        }
    }

    public function me(Request $request)
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return $this->error('No token provided', [], 401);
            }

            $result = $this->authService->getAuthenticatedUser($token);

            return $this->success($result, 'User information retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to get user information');
        }
    }

    public function sessions(Request $request)
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return $this->error('No token provided', [], 401);
            }

            $user = $this->getUserFromToken($token);
            $result = $this->authService->getUserSessions($user->id);

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'sessions' => $result['sessions'],
                'active_sessions_count' => $result['sessions']->count(),
            ], 'User sessions retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch user sessions');
        }
    }

    public function destroySession($id)
    {
        try {
            $token = request()->bearerToken();
            
            if (!$token) {
                return $this->error('No token provided', [], 401);
            }

            $user = $this->getUserFromToken($token);
            $this->authService->terminateSession($user->id, $id);

            return $this->deleted('Session terminated successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to terminate session');
        }
    }

    public function activeLocks(Request $request)
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return $this->error('No token provided', [], 401);
            }

            $user = $this->getUserFromToken($token);
            $result = $this->authService->getUserLocks($user->id);

            return $this->success([
                'user_id' => $result['user']->id,
                'username' => $result['user']->username,
                'active_locks' => $result['locks'],
                'locks_count' => $result['locks']->count(),
            ], 'Active user locks retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch active locks');
        }
    }

    protected function getUserFromToken(string $token)
    {
        $user = \EvolutionCMS\Models\User::where('access_token', $token)->first();
        if (!$user) {
            throw new \Exception('Invalid token');
        }
        return $user;
    }
}