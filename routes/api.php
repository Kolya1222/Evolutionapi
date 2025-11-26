<?php

use Illuminate\Support\Facades\Route;
use EvolutionCMS\Evolutionapi\Controllers\Content\DocumentController;
use EvolutionCMS\Evolutionapi\Controllers\Content\CategoryController;
use EvolutionCMS\Evolutionapi\Controllers\Content\DocumentGroupController;
use EvolutionCMS\Evolutionapi\Controllers\Content\ClosureController;
use EvolutionCMS\Evolutionapi\Controllers\Users\UserController;
use EvolutionCMS\Evolutionapi\Controllers\Users\AuthController;
use EvolutionCMS\Evolutionapi\Controllers\Users\RoleController;
use EvolutionCMS\Evolutionapi\Controllers\Users\MemberGroupController;
use EvolutionCMS\Evolutionapi\Controllers\Users\PermissionController;
use EvolutionCMS\Evolutionapi\Controllers\Templates\TemplateController;
use EvolutionCMS\Evolutionapi\Controllers\Templates\TvController;
use EvolutionCMS\Evolutionapi\Controllers\Templates\TvValueController;
use EvolutionCMS\Evolutionapi\Controllers\Elements\ChunkController;
use EvolutionCMS\Evolutionapi\Controllers\Elements\SnippetController;
use EvolutionCMS\Evolutionapi\Controllers\Elements\PluginController;
use EvolutionCMS\Evolutionapi\Controllers\Elements\ModuleController;
use EvolutionCMS\Evolutionapi\Controllers\Elements\EventController;
use EvolutionCMS\Evolutionapi\Controllers\System\LogController;
use EvolutionCMS\Evolutionapi\Controllers\System\SystemController;

Route::prefix('api')->group(function () {
    Route::prefix('contents')->group(function () {
        Route::prefix('documents')->group(function () {
            Route::get('/', [DocumentController::class, 'index']);
            Route::post('/', [DocumentController::class, 'store']);
            Route::get('/tree', [DocumentController::class, 'tree']);
            Route::get('/tree/{id}', [DocumentController::class, 'tree']);
            Route::get('/{id}', [DocumentController::class, 'show']);
            Route::put('/{id}', [DocumentController::class, 'update']);
            Route::delete('/{id}', [DocumentController::class, 'destroy']);
            Route::put('/{id}/restore', [DocumentController::class, 'restore']);
            Route::put('/{id}/move', [DocumentController::class, 'move']);
            Route::get('/{id}/children', [DocumentController::class, 'children']);
            Route::get('/{id}/siblings', [DocumentController::class, 'siblings']);
            Route::get('/{id}/ancestors', [DocumentController::class, 'ancestors']);
            Route::get('/{id}/descendants', [DocumentController::class, 'descendants']);
            Route::get('/search', [DocumentController::class, 'search']);
            Route::post('/advanced-search', [DocumentController::class, 'advancedSearch']);
            Route::post('/publish-all', [DocumentController::class, 'publishAll']);
            Route::post('/unpublish-all', [DocumentController::class, 'unpublishAll']);
            Route::post('/update-tree', [DocumentController::class, 'updateTree']);
        });

        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::post('/', [CategoryController::class, 'store']);
            Route::get('/{id}', [CategoryController::class, 'show']);
            Route::put('/{id}', [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
            Route::get('/{id}/elements', [CategoryController::class, 'elements']);
            Route::get('/{id}/elements/{type}', [CategoryController::class, 'elements']);
            Route::post('/{id}/move-elements', [CategoryController::class, 'moveElements']);
        });

        Route::prefix('document-groups')->group(function () {
            Route::get('/', [DocumentGroupController::class, 'index']);
            Route::post('/', [DocumentGroupController::class, 'store']);
            Route::get('/{id}', [DocumentGroupController::class, 'show']);
            Route::put('/{id}', [DocumentGroupController::class, 'update']);
            Route::delete('/{id}', [DocumentGroupController::class, 'destroy']);
            Route::get('/{id}/documents', [DocumentGroupController::class, 'documents']);
            Route::post('/{id}/documents', [DocumentGroupController::class, 'attachDocuments']);
            Route::post('/{id}/sync-documents', [DocumentGroupController::class, 'syncDocuments']);
            Route::delete('/{id}/documents/{documentId}', [DocumentGroupController::class, 'detachDocument']);
        });

        Route::prefix('closures')->group(function () {
            Route::get('/', [ClosureController::class, 'index']);
            Route::post('/', [ClosureController::class, 'store']);
            Route::get('/stats', [ClosureController::class, 'stats']);
            Route::get('/{id}', [ClosureController::class, 'show']);
            Route::put('/{id}', [ClosureController::class, 'update']);
            Route::delete('/{id}', [ClosureController::class, 'destroy']);
            Route::post('/create-relationship', [ClosureController::class, 'createRelationship']);
            Route::get('/documents/{documentId}/ancestors', [ClosureController::class, 'ancestors']);
            Route::get('/documents/{documentId}/descendants', [ClosureController::class, 'descendants']);
            Route::get('/documents/{documentId}/path', [ClosureController::class, 'path']);
            Route::get('/documents/{documentId}/subtree', [ClosureController::class, 'subtree']);
            Route::put('/documents/{documentId}/move', [ClosureController::class, 'moveNode']);
        });
    });

    Route::prefix('users')->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::put('/{id}/block', [UserController::class, 'block']);
            Route::put('/{id}/unblock', [UserController::class, 'unblock']);
            Route::get('/{id}/settings', [UserController::class, 'settings']);
            Route::get('/{id}/groups', [UserController::class, 'groups']);
            Route::get('/{id}/tv-values', [UserController::class, 'tvValues']);
        });

        Route::prefix('auths')->group(function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/sessions', [AuthController::class, 'sessions']);
            Route::delete('/sessions/{id}', [AuthController::class, 'destroySession']);
            Route::get('/active-locks', [AuthController::class, 'activeLocks']);
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
            Route::get('/{id}', [RoleController::class, 'show']);
            Route::put('/{id}', [RoleController::class, 'update']);
            Route::delete('/{id}', [RoleController::class, 'destroy']);
            Route::get('/{id}/permissions', [RoleController::class, 'permissions']);
            Route::get('/{id}/tv-access', [RoleController::class, 'tvAccess']);
            Route::post('/{id}/tv-access', [RoleController::class, 'addTvAccess']);
            Route::delete('/{id}/tv-access/{tmplvarid}', [RoleController::class, 'removeTvAccess']);
            Route::get('/{id}/users', [RoleController::class, 'users']);
        });

        Route::prefix('member-groups')->group(function () {
            Route::get('/', [MemberGroupController::class, 'index']);
            Route::post('/', [MemberGroupController::class, 'store']);
            Route::get('/{id}', [MemberGroupController::class, 'show']);
            Route::put('/{id}', [MemberGroupController::class, 'update']);
            Route::delete('/{id}', [MemberGroupController::class, 'destroy']);
            Route::get('/{id}/users', [MemberGroupController::class, 'users']);
            Route::post('/{id}/users', [MemberGroupController::class, 'addUser']);
            Route::delete('/{id}/users/{userId}', [MemberGroupController::class, 'removeUser']);
            Route::get('/{id}/document-groups', [MemberGroupController::class, 'documentGroups']);
            Route::post('/{id}/document-groups', [MemberGroupController::class, 'addDocumentGroup']);
            Route::delete('/{id}/document-groups/{docGroupId}', [MemberGroupController::class, 'removeDocumentGroup']);
            Route::get('/users/{userId}/groups', [MemberGroupController::class, 'userGroups']);
        });

        Route::prefix('permissions')->group(function () {
            Route::get('/groups', [PermissionController::class, 'groupsIndex']);
            Route::post('/groups', [PermissionController::class, 'groupsStore']);
            Route::get('/groups/{id}', [PermissionController::class, 'groupsShow']);
            Route::put('/groups/{id}', [PermissionController::class, 'groupsUpdate']);
            Route::delete('/groups/{id}', [PermissionController::class, 'groupsDestroy']);
            Route::get('/groups/{id}/permissions', [PermissionController::class, 'groupPermissions']);
            Route::get('/', [PermissionController::class, 'permissionsIndex']);
            Route::post('/', [PermissionController::class, 'permissionsStore']);
            Route::get('/{id}', [PermissionController::class, 'permissionsShow']);
            Route::put('/{id}', [PermissionController::class, 'permissionsUpdate']);
            Route::delete('/{id}', [PermissionController::class, 'permissionsDestroy']);
            Route::put('/{id}/move', [PermissionController::class, 'movePermission']);
            Route::put('/{id}/enable', [PermissionController::class, 'enablePermission']);
            Route::put('/{id}/disable', [PermissionController::class, 'disablePermission']);
        });
    });

    Route::prefix('templates')->group(function () {
        Route::prefix('templates')->group(function () {
            Route::get('/', [TemplateController::class, 'index']);
            Route::post('/', [TemplateController::class, 'store']);
            Route::get('/{id}', [TemplateController::class, 'show']);
            Route::put('/{id}', [TemplateController::class, 'update']);
            Route::delete('/{id}', [TemplateController::class, 'destroy']);
            Route::post('/{id}/duplicate', [TemplateController::class, 'duplicate']);
            Route::get('/{id}/content', [TemplateController::class, 'content']);
            Route::get('/{id}/tvs', [TemplateController::class, 'tvs']);
            Route::post('/{id}/tvs', [TemplateController::class, 'addTv']);
            Route::delete('/{id}/tvs/{tvId}', [TemplateController::class, 'removeTv']);
        });

        Route::prefix('tvs')->group(function () {
            Route::get('/', [TvController::class, 'index']);
            Route::post('/', [TvController::class, 'store']);
            Route::get('/{id}', [TvController::class, 'show']);
            Route::put('/{id}', [TvController::class, 'update']);
            Route::delete('/{id}', [TvController::class, 'destroy']);
            Route::post('/{id}/duplicate', [TvController::class, 'duplicate']);
            Route::get('/{id}/templates', [TvController::class, 'templates']);
            Route::post('/{id}/templates', [TvController::class, 'addTemplate']);
            Route::delete('/{id}/templates/{templateId}', [TvController::class, 'removeTemplate']);
            Route::get('/{id}/access', [TvController::class, 'access']);
            Route::post('/{id}/access', [TvController::class, 'addAccess']);
            Route::delete('/{id}/access/{accessId}', [TvController::class, 'removeAccess']);
        });

        Route::prefix('tv-values')->group(function () {
            Route::get('/', [TvValueController::class, 'index']);
            Route::post('/', [TvValueController::class, 'store']);
            Route::get('/{id}', [TvValueController::class, 'show']);
            Route::put('/{id}', [TvValueController::class, 'update']);
            Route::delete('/{id}', [TvValueController::class, 'destroy']);
            Route::get('/documents/{documentId}', [TvValueController::class, 'byDocument']);
            Route::post('/documents/{documentId}', [TvValueController::class, 'setDocumentTvValue']);
            Route::post('/documents/{documentId}/multiple', [TvValueController::class, 'setMultipleDocumentTvValues']);
            Route::delete('/documents/{documentId}/{tmplvarId}', [TvValueController::class, 'deleteDocumentTvValue']);
            Route::delete('/documents/{documentId}', [TvValueController::class, 'clearDocumentTvValues']);
            Route::get('/tmplvars/{tmplvarId}', [TvValueController::class, 'byTmplvar']);
        });
    });

    Route::prefix('elements')->group(function () {
        Route::prefix('chunks')->group(function () {
            Route::get('/', [ChunkController::class, 'index']);
            Route::post('/', [ChunkController::class, 'store']);
            Route::get('/{id}', [ChunkController::class, 'show']);
            Route::put('/{id}', [ChunkController::class, 'update']);
            Route::delete('/{id}', [ChunkController::class, 'destroy']);
            Route::post('/{id}/duplicate', [ChunkController::class, 'duplicate']);
            Route::put('/{id}/enable', [ChunkController::class, 'enable']);
            Route::put('/{id}/disable', [ChunkController::class, 'disable']);
            Route::put('/{id}/lock', [ChunkController::class, 'lock']);
            Route::put('/{id}/unlock', [ChunkController::class, 'unlock']);
            Route::get('/{id}/content', [ChunkController::class, 'content']);
            Route::put('/{id}/content', [ChunkController::class, 'updateContent']);
            Route::post('/{id}/execute', [ChunkController::class, 'execute']);
        });

        Route::prefix('snippets')->group(function () {
            Route::get('/', [SnippetController::class, 'index']);
            Route::post('/', [SnippetController::class, 'store']);
            Route::get('/{id}', [SnippetController::class, 'show']);
            Route::put('/{id}', [SnippetController::class, 'update']);
            Route::delete('/{id}', [SnippetController::class, 'destroy']);
            Route::post('/{id}/duplicate', [SnippetController::class, 'duplicate']);
            Route::put('/{id}/enable', [SnippetController::class, 'enable']);
            Route::put('/{id}/disable', [SnippetController::class, 'disable']);
            Route::put('/{id}/lock', [SnippetController::class, 'lock']);
            Route::put('/{id}/unlock', [SnippetController::class, 'unlock']);
            Route::get('/{id}/content', [SnippetController::class, 'content']);
            Route::put('/{id}/content', [SnippetController::class, 'updateContent']);
            Route::get('/{id}/properties', [SnippetController::class, 'properties']);
            Route::put('/{id}/properties', [SnippetController::class, 'updateProperties']);
            Route::post('/{id}/execute', [SnippetController::class, 'execute']);
            Route::post('/{id}/attach-module', [SnippetController::class, 'attachModule']);
            Route::delete('/{id}/detach-module', [SnippetController::class, 'detachModule']);
        });

        Route::prefix('plugins')->group(function () {
            Route::get('/', [PluginController::class, 'index']);
            Route::post('/', [PluginController::class, 'store']);
            Route::get('/{id}', [PluginController::class, 'show']);
            Route::put('/{id}', [PluginController::class, 'update']);
            Route::delete('/{id}', [PluginController::class, 'destroy']);
            Route::post('/{id}/duplicate', [PluginController::class, 'duplicate']);
            Route::put('/{id}/enable', [PluginController::class, 'enable']);
            Route::put('/{id}/disable', [PluginController::class, 'disable']);
            Route::put('/{id}/lock', [PluginController::class, 'lock']);
            Route::put('/{id}/unlock', [PluginController::class, 'unlock']);
            Route::get('/{id}/content', [PluginController::class, 'content']);
            Route::put('/{id}/content', [PluginController::class, 'updateContent']);
            Route::get('/{id}/properties', [PluginController::class, 'properties']);
            Route::put('/{id}/properties', [PluginController::class, 'updateProperties']);
            Route::get('/{id}/events', [PluginController::class, 'events']);
            Route::post('/{id}/events', [PluginController::class, 'addEvent']);
            Route::delete('/{id}/events/{eventId}', [PluginController::class, 'removeEvent']);
            Route::get('/{id}/alternative', [PluginController::class, 'alternative']);
        });

        Route::prefix('modules')->group(function () {
            Route::get('/', [ModuleController::class, 'index']);
            Route::post('/', [ModuleController::class, 'store']);
            Route::get('/{id}', [ModuleController::class, 'show']);
            Route::put('/{id}', [ModuleController::class, 'update']);
            Route::delete('/{id}', [ModuleController::class, 'destroy']);
            Route::post('/{id}/duplicate', [ModuleController::class, 'duplicate']);
            Route::put('/{id}/enable', [ModuleController::class, 'enable']);
            Route::put('/{id}/disable', [ModuleController::class, 'disable']);
            Route::put('/{id}/lock', [ModuleController::class, 'lock']);
            Route::put('/{id}/unlock', [ModuleController::class, 'unlock']);
            Route::get('/{id}/content', [ModuleController::class, 'content']);
            Route::put('/{id}/content', [ModuleController::class, 'updateContent']);
            Route::get('/{id}/properties', [ModuleController::class, 'properties']);
            Route::put('/{id}/properties', [ModuleController::class, 'updateProperties']);
            Route::get('/{id}/access', [ModuleController::class, 'access']);
            Route::post('/{id}/access', [ModuleController::class, 'addAccess']);
            Route::delete('/{id}/access/{usergroupId}', [ModuleController::class, 'removeAccess']);
            Route::get('/{id}/dependencies', [ModuleController::class, 'dependencies']);
            Route::post('/{id}/dependencies', [ModuleController::class, 'addDependency']);
            Route::delete('/{id}/dependencies/{dependencyId}', [ModuleController::class, 'removeDependency']);
            Route::post('/{id}/execute', [ModuleController::class, 'execute']);
        });

        Route::prefix('events')->group(function () {
            Route::get('/', [EventController::class, 'index']);
            Route::post('/', [EventController::class, 'store']);
            Route::get('/groups', [EventController::class, 'groups']);
            Route::get('/groups/{groupName}', [EventController::class, 'byGroup']);
            Route::get('/services', [EventController::class, 'services']);
            Route::get('/services/{service}', [EventController::class, 'byService']);
            Route::get('/{id}', [EventController::class, 'show']);
            Route::put('/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
            Route::get('/{id}/plugins', [EventController::class, 'plugins']);
            Route::post('/{id}/plugins', [EventController::class, 'addPlugin']);
            Route::delete('/{id}/plugins/{pluginId}', [EventController::class, 'removePlugin']);
            Route::put('/{id}/plugins/{pluginId}/priority', [EventController::class, 'updatePluginPriority']);
        });
    });

    Route::prefix('systems')->group(function () {
        Route::prefix('logs')->group(function () {
            Route::get('/events', [LogController::class, 'eventLogs']);
            Route::get('/events/stats', [LogController::class, 'eventLogStats']);
            Route::post('/events', [LogController::class, 'createEventLog']);
            Route::get('/events/{id}', [LogController::class, 'showEventLog']);
            Route::delete('/events/{id}', [LogController::class, 'deleteEventLog']);
            Route::delete('/events', [LogController::class, 'clearEventLogs']);
            Route::get('/manager', [LogController::class, 'managerLogs']);
            Route::get('/manager/stats', [LogController::class, 'managerLogStats']);
            Route::post('/manager', [LogController::class, 'createManagerLog']);
            Route::get('/manager/{id}', [LogController::class, 'showManagerLog']);
            Route::delete('/manager/{id}', [LogController::class, 'deleteManagerLog']);
            Route::delete('/manager', [LogController::class, 'clearManagerLogs']);
        });

        Route::prefix('settings')->group(function () {
            Route::get('/', [SystemController::class, 'index']);
            Route::post('/', [SystemController::class, 'store']);
            Route::get('/groups', [SystemController::class, 'groups']);
            Route::get('/groups/{groupName}', [SystemController::class, 'byGroup']);
            Route::get('/multiple', [SystemController::class, 'getMultiple']);
            Route::post('/multiple', [SystemController::class, 'updateMultiple']);
            Route::post('/validate', [SystemController::class, 'validateSettings']);
            Route::get('/{name}', [SystemController::class, 'show']);
            Route::put('/{name}', [SystemController::class, 'update']);
            Route::delete('/{name}', [SystemController::class, 'destroy']);
        });
    });
});