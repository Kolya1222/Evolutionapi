<?php

namespace roilafx\Evolutionapi\Services\Users;

use roilafx\Evolutionapi\Services\BaseService;
use EvolutionCMS\Models\ActiveUser;
use EvolutionCMS\Models\ActiveUserSession;
use EvolutionCMS\Models\ActiveUserLock;
use EvolutionCMS\Models\User;
use EvolutionCMS\Models\UserAttribute;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class AuthService extends BaseService
{
    protected $sessionTimeout;

    public function __construct()
    {
        parent::__construct();
        $this->sessionTimeout = $this->core->getConfig('session_timeout', 7200);
    }

    public function login(array $credentials): array
    {
        // Вызываем событие перед логином
        $eventParams = [
            'username' => $credentials['username'],
            'userpassword' => $credentials['password'],
            'rememberme' => $credentials['remember_me'] ?? false,
            'lp' => $credentials['remember_me'] ?? false,
        ];
        
        $this->invokeEvent('OnBeforeUserLogin', $eventParams);

        // Проверяем, не отменило ли событие логин
        if (isset($eventParams['blockLogin']) && $eventParams['blockLogin']) {
            throw new Exception('Login cancelled by event');
        }

        // Находим пользователя
        $user = User::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // Увеличиваем счетчик неудачных попыток
            if ($user) {
                $this->incrementFailedLoginCount($user);
            }
            throw new Exception('Invalid credentials');
        }

        // Проверяем блокировку через атрибуты
        if ($this->isUserBlocked($user)) {
            throw new Exception('Account is blocked');
        }

        // Создаем сессию
        $sessionData = $this->createUserSession($user, request()->ip());

        // Обновляем информацию о входе
        $this->updateLoginInfo($user, $sessionData['session_id']);

        // Генерируем токены
        $tokens = $this->generateTokens($user);

        // Вызываем событие после успешного логина
        $this->invokeEvent('OnUserLogin', [
            'user' => $user,
            'username' => $user->username,
            'userid' => $user->id,
            'userpassword' => $credentials['password'],
            'rememberme' => $credentials['remember_me'] ?? false,
        ]);

        // Логируем действие
        $this->logManagerAction('user_login', $user->id, $user->username);

        return [
            'user' => $this->formatUser($user),
            'session' => $sessionData,
            'tokens' => $tokens
        ];
    }

    public function logout(string $token): bool
    {
        $user = User::where('access_token', $token)->first();

        if ($user) {
            // Вызываем событие перед логаутом
            $this->invokeEvent('OnBeforeUserLogout', [
                'user' => $user,
                'userid' => $user->id,
                'username' => $user->username,
            ]);

            $this->cleanupUserSession($user);
            $this->clearUserTokens($user);

            // Вызываем событие после логаута
            $this->invokeEvent('OnUserLogout', [
                'user' => $user,
                'userid' => $user->id,
                'username' => $user->username,
            ]);

            // Логируем действие
            $this->logManagerAction('user_logout', $user->id, $user->username);
        }

        return true;
    }

    public function refreshToken(string $refreshToken): array
    {
        $user = User::where('refresh_token', $refreshToken)->first();

        if (!$user) {
            throw new Exception('Invalid refresh token');
        }

        // Проверяем срок действия refresh token
        if ($user->valid_to && $user->valid_to < time()) {
            throw new Exception('Refresh token expired');
        }

        // Генерируем новые токены
        $newTokens = $this->generateTokens($user);

        return $newTokens;
    }

    public function getAuthenticatedUser(string $token): array
    {
        $user = User::with(['attributes', 'memberGroups.group'])
            ->where('access_token', $token)
            ->first();

        if (!$user) {
            throw new Exception('Invalid token');
        }

        // Проверяем активность сессии
        $activeSession = ActiveUserSession::where('internalKey', $user->id)->first();
        if (!$activeSession) {
            throw new Exception('Session expired');
        }

        // Обновляем время последнего действия
        $currentTime = time();
        $this->updateSessionActivity($user->id, $currentTime);

        return [
            'user' => $this->formatUser($user),
            'session' => [
                'session_id' => $activeSession->sid,
                'last_activity' => $this->safeFormatDate($currentTime),
                'ip' => $activeSession->ip,
            ]
        ];
    }

    public function getUserSessions(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        $sessions = ActiveUserSession::where('internalKey', $userId)
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

        return [
            'user' => $user,
            'sessions' => $sessions
        ];
    }

    public function terminateSession(int $userId, string $sessionId): bool
    {
        $currentToken = request()->bearerToken();
        
        // Не позволяем удалить текущую сессию
        if ($sessionId === $currentToken) {
            throw new Exception('Cannot delete current session');
        }

        // Находим сессию для удаления
        $session = ActiveUserSession::where('sid', $sessionId)
            ->where('internalKey', $userId)
            ->first();

        if (!$session) {
            throw new Exception('Session not found');
        }

        // Удаляем связанные записи
        ActiveUser::where('sid', $sessionId)->delete();
        ActiveUserLock::where('sid', $sessionId)->delete();
        $session->delete();

        // Логируем действие
        $this->logManagerAction('session_terminate', $userId, "Session terminated");

        return true;
    }

    public function getUserLocks(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        $locks = ActiveUserLock::where('internalKey', $userId)
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

        return [
            'user' => $user,
            'locks' => $locks
        ];
    }

    protected function isUserBlocked(User $user): bool
    {
        $attributes = $user->attributes;
        if (!$attributes) {
            return false;
        }

        return (bool) $attributes->blocked;
    }

    protected function incrementFailedLoginCount(User $user): void
    {
        $attributes = $user->attributes;
        if ($attributes) {
            $attributes->increment('failedlogincount');
        }
    }

    protected function createUserSession(User $user, string $ip): array
    {
        $sessionId = $this->generateSessionId();
        $currentTime = time();

        // Активная пользовательская сессия
        ActiveUserSession::create([
            'sid' => $sessionId,
            'internalKey' => $user->id,
            'lasthit' => $currentTime,
            'ip' => $ip,
        ]);

        // Активный пользователь
        ActiveUser::create([
            'sid' => $sessionId,
            'internalKey' => $user->id,
            'username' => $user->username,
            'lasthit' => $currentTime,
            'action' => 'login',
            'id' => 0,
        ]);

        return [
            'session_id' => $sessionId,
            'created_at' => $currentTime,
            'ip' => $ip,
        ];
    }

    protected function updateLoginInfo(User $user, string $sessionId): void
    {
        $attributes = $user->attributes;
        if (!$attributes) {
            // Создаем запись атрибутов, если её нет
            $attributes = UserAttribute::create([
                'internalKey' => $user->id,
                'fullname' => $user->username,
                'email' => '',
                'logincount' => 1,
                'lastlogin' => 0,
                'thislogin' => time(),
                'failedlogincount' => 0,
                'sessionid' => $sessionId,
                'createdon' => time(),
                'editedon' => time(),
            ]);
        } else {
            $currentTime = time();
            $attributes->update([
                'lastlogin' => $attributes->thislogin,
                'thislogin' => $currentTime,
                'logincount' => $attributes->logincount + 1,
                'failedlogincount' => 0,
                'sessionid' => $sessionId,
                'editedon' => $currentTime,
            ]);
        }
    }

    protected function updateSessionActivity(int $userId, int $timestamp): void
    {
        ActiveUserSession::where('internalKey', $userId)
            ->update(['lasthit' => $timestamp]);
            
        ActiveUser::where('internalKey', $userId)
            ->update(['lasthit' => $timestamp]);
    }

    protected function cleanupUserSession(User $user): void
    {
        // Удаляем активные записи
        ActiveUser::where('internalKey', $user->id)->delete();
        ActiveUserSession::where('internalKey', $user->id)->delete();
        ActiveUserLock::where('internalKey', $user->id)->delete();

        // Очищаем sessionid в атрибутах
        $attributes = $user->attributes;
        if ($attributes) {
            $attributes->update(['sessionid' => '']);
        }
    }

    protected function clearUserTokens(User $user): void
    {
        $user->update([
            'access_token' => null,
            'refresh_token' => null,
            'valid_to' => null,
        ]);
    }

    protected function generateTokens(User $user): array
    {
        $accessToken = $this->generateAccessToken();
        $refreshToken = $this->generateRefreshToken();
        $validTo = now()->addDays(30)->timestamp;

        $user->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'valid_to' => $validTo,
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ];
    }

    protected function formatUser(User $user): array
    {
        $attributes = $user->attributes;

        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $attributes->email ?? null,
            'fullname' => $attributes->fullname ?? null,
            'first_name' => $attributes->first_name ?? null,
            'middle_name' => $attributes->middle_name ?? null,
            'last_name' => $attributes->last_name ?? null,
            'role' => $attributes->role ?? 0,
            'blocked' => (bool)($attributes->blocked ?? false),
            'verified' => (bool)($attributes->verified ?? true),
            'last_login' => $this->safeFormatDate($attributes->lastlogin ?? null),
            'this_login' => $this->safeFormatDate($attributes->thislogin ?? null),
            'login_count' => $attributes->logincount ?? 0,
            'failed_login_count' => $attributes->failedlogincount ?? 0,
            'member_groups' => $user->memberGroups->map(function($memberGroup) {
                return [
                    'id' => $memberGroup->group->id,
                    'name' => $memberGroup->group->name,
                ];
            })->toArray(),
        ];
    }

    protected function generateSessionId(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    protected function generateAccessToken(): string
    {
        return Str::random(60);
    }

    protected function generateRefreshToken(): string
    {
        return Str::random(80);
    }
}