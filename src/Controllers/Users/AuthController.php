<?php

namespace EvolutionCMS\Evolutionapi\Controllers\Users;

use EvolutionCMS\Evolutionapi\Controllers\ApiController;
use EvolutionCMS\Models\ActiveUser;
use EvolutionCMS\Models\ActiveUserSession;
use EvolutionCMS\Models\ActiveUserLock;
use EvolutionCMS\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        try {
            $validated = $this->validateRequest($request, [
                'username' => 'required|string|max:255',
                'password' => 'required|string',
                'remember_me' => 'nullable|boolean',
            ]);

            // Находим пользователя
            $user = User::where('username', $validated['username'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return $this->error('Invalid credentials', [], 401);
            }

            // Проверяем блокировку через атрибуты
            $userAttributes = $user->attributes;
            if ($userAttributes && $userAttributes->blocked) {
                return $this->error('Account is blocked', [], 403);
            }

            // Создаем сессию
            $sessionId = $this->generateSessionId();
            $currentTime = time();

            // Активная пользовательская сессия
            ActiveUserSession::create([
                'sid' => $sessionId,
                'internalKey' => $user->id,
                'lasthit' => $currentTime,
                'ip' => $request->ip(),
            ]);

            // Активный пользователь
            ActiveUser::create([
                'sid' => $sessionId,
                'internalKey' => $user->id,
                'username' => $user->username,
                'lasthit' => $currentTime,
                'action' => 'login',
                'id' => 0, // ID элемента, если применимо
            ]);

            // Обновляем время последнего входа в атрибутах
            if ($userAttributes) {
                $userAttributes->update([
                    'lastlogin' => $userAttributes->thislogin,
                    'thislogin' => $currentTime,
                    'logincount' => $userAttributes->logincount + 1,
                    'failedlogincount' => 0, // Сбрасываем счетчик неудачных попыток
                    'sessionid' => $sessionId,
                ]);
            }

            // Генерируем токены
            $accessToken = $this->generateAccessToken();
            $refreshToken = $this->generateRefreshToken();

            // Сохраняем токены в пользователе
            $user->update([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'valid_to' => now()->addDays(30)->timestamp,
            ]);

            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $userAttributes->email ?? null,
                    'fullname' => $userAttributes->fullname ?? null,
                ],
                'tokens' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => 3600, // 1 час
                    'session_id' => $sessionId,
                ],
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

            // Находим пользователя по токену
            $user = User::where('access_token', $token)->first();

            if ($user) {
                // Удаляем активные записи
                ActiveUser::where('internalKey', $user->id)->delete();
                ActiveUserSession::where('internalKey', $user->id)->delete();
                ActiveUserLock::where('internalKey', $user->id)->delete();

                // Очищаем токены
                $user->update([
                    'access_token' => null,
                    'refresh_token' => null,
                    'valid_to' => null,
                ]);

                // Очищаем sessionid в атрибутах
                if ($user->attributes) {
                    $user->attributes->update(['sessionid' => '']);
                }
            }

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

            $user = User::where('refresh_token', $validated['refresh_token'])->first();

            if (!$user) {
                return $this->error('Invalid refresh token', [], 401);
            }

            // Проверяем срок действия refresh token
            if ($user->valid_to && $user->valid_to < time()) {
                return $this->error('Refresh token expired', [], 401);
            }

            // Генерируем новые токены
            $newAccessToken = $this->generateAccessToken();
            $newRefreshToken = $this->generateRefreshToken();

            $user->update([
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'valid_to' => now()->addDays(30)->timestamp,
            ]);

            return $this->success([
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 'Token refreshed successfully');

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

            $user = User::with(['attributes', 'memberGroups.group'])
                ->where('access_token', $token)
                ->first();

            if (!$user) {
                return $this->error('Invalid token', [], 401);
            }

            // Проверяем активность сессии
            $activeSession = ActiveUserSession::where('internalKey', $user->id)->first();
            if (!$activeSession) {
                return $this->error('Session expired', [], 401);
            }

            // Обновляем время последнего действия
            $currentTime = time();
            $activeSession->update(['lasthit' => $currentTime]);
            
            ActiveUser::where('internalKey', $user->id)
                ->update(['lasthit' => $currentTime]);

            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->attributes->email ?? null,
                    'fullname' => $user->attributes->fullname ?? null,
                    'role' => $user->attributes->role ?? 0,
                    'blocked' => (bool)($user->attributes->blocked ?? false),
                    'verified' => (bool)($user->attributes->verified ?? true),
                ],
                'session' => [
                    'session_id' => $activeSession->sid,
                    'last_activity' => $this->safeFormatDate($currentTime),
                    'ip' => $activeSession->ip,
                ],
            ], 'User information retrieved successfully');

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

            $user = User::where('access_token', $token)->first();

            if (!$user) {
                return $this->error('Invalid token', [], 401);
            }

            $sessions = ActiveUserSession::where('internalKey', $user->id)
                ->orderBy('lasthit', 'desc')
                ->get()
                ->map(function($session) {
                    return [
                        'session_id' => $session->sid,
                        'ip' => $session->ip,
                        'last_activity' => $this->safeFormatDate($session->lasthit),
                        'is_current' => $session->sid === request()->bearerToken(),
                    ];
                });

            return $this->success([
                'user_id' => $user->id,
                'username' => $user->username,
                'sessions' => $sessions,
                'active_sessions_count' => $sessions->count(),
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

            $currentUser = User::where('access_token', $token)->first();

            if (!$currentUser) {
                return $this->error('Invalid token', [], 401);
            }

            // Находим сессию для удаления
            $session = ActiveUserSession::where('sid', $id)
                ->where('internalKey', $currentUser->id)
                ->first();

            if (!$session) {
                return $this->notFound('Session not found');
            }

            // Не позволяем удалить текущую сессию
            if ($session->sid === $token) {
                return $this->error('Cannot delete current session', [], 422);
            }

            // Удаляем связанные записи
            ActiveUser::where('sid', $id)->delete();
            ActiveUserLock::where('sid', $id)->delete();
            $session->delete();

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

            $user = User::where('access_token', $token)->first();

            if (!$user) {
                return $this->error('Invalid token', [], 401);
            }

            $locks = ActiveUserLock::where('internalKey', $user->id)
                ->orderBy('lasthit', 'desc')
                ->get()
                ->map(function($lock) {
                    return [
                        'id' => $lock->id,
                        'element_type' => $lock->elementType,
                        'element_id' => $lock->elementId,
                        'last_activity' => $this->safeFormatDate($lock->lasthit),
                        'session_id' => $lock->sid,
                    ];
                });

            return $this->success([
                'user_id' => $user->id,
                'username' => $user->username,
                'active_locks' => $locks,
                'locks_count' => $locks->count(),
            ], 'Active user locks retrieved successfully');

        } catch (\Exception $e) {
            return $this->exceptionError($e, 'Failed to fetch active locks');
        }
    }

    protected function generateSessionId()
    {
        return md5(uniqid(mt_rand(), true));
    }

    protected function generateAccessToken()
    {
        return Str::random(60);
    }

    protected function generateRefreshToken()
    {
        return Str::random(80);
    }

    protected function safeFormatDate($dateValue)
    {
        if (!$dateValue) return null;
        if ($dateValue instanceof \Illuminate\Support\Carbon) {
            return $dateValue->format('Y-m-d H:i:s');
        }
        if (is_numeric($dateValue) && $dateValue > 0) {
            return date('Y-m-d H:i:s', $dateValue);
        }
        return null;
    }
}