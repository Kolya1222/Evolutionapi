<?php

namespace roilafx\Evolutionapi\Controllers\Users;

use roilafx\Evolutionapi\Controllers\ApiController;
use roilafx\Evolutionapi\Services\Users\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Authentication',
    description: 'Аутентификация и управление сессиями пользователей'
)]
class AuthController extends ApiController
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[OA\Post(
        path: '/api/users/auths/login',
        summary: 'Вход пользователя',
        description: 'Аутентификация пользователя по логину и паролю',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', maxLength: 255, example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', example: 'password'),
                    new OA\Property(property: 'remember_me', type: 'boolean', nullable: true, example: false)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 423, ref: '#/components/responses/409'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

    #[OA\Post(
        path: '/api/users/auths/logout',
        summary: 'Выход пользователя',
        description: 'Завершение текущей сессии пользователя',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

    #[OA\Post(
        path: '/api/users/auths/refresh',
        summary: 'Обновление токена',
        description: 'Обновление access токена с использованием refresh токена',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refresh_token'],
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string', example: 'refresh_token_string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 422, ref: '#/components/responses/422'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

    #[OA\Get(
        path: '/api/users/auths/me',
        summary: 'Информация о текущем пользователе',
        description: 'Возвращает информацию о текущем аутентифицированном пользователе',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

    #[OA\Get(
        path: '/api/users/auths/sessions',
        summary: 'Активные сессии пользователя',
        description: 'Возвращает список активных сессий текущего пользователя',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

    #[OA\Delete(
        path: '/api/users/auths/sessions/{id}',
        summary: 'Завершить сессию',
        description: 'Завершает указанную сессию пользователя (кроме текущей)',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID сессии (токен)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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

    #[OA\Get(
        path: '/api/users/auths/locks',
        summary: 'Активные блокировки пользователя',
        description: 'Возвращает список активных блокировок элементов текущим пользователем',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/200'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 500, ref: '#/components/responses/500')
        ]
    )]
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