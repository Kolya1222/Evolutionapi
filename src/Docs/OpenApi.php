<?php

namespace roilafx\Evolutionapi\Docs;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
    info: new OA\Info(
        version: '1.0.0',
        title: 'Evolution CMS API',
        description: 'REST API для управления Evolution CMS',
        contact: new OA\Contact(
            name: 'API Support',
            email: 'belov.belov-ik@yandex.ru'
        )
    ),
    servers: [
        new OA\Server(
            url: '{url}',
            description: 'Основной сервер API',
            variables: [
                new OA\ServerVariable(
                    'url',
                    default: MODX_SITE_URL,
                    description: 'Домен сервера'
                )
            ]
        ),
    ],
    tags: [
        new OA\Tag(name: 'Documents', description: 'Управление документами'),
        new OA\Tag(name: 'Categories', description: 'Управление категориями'),
        new OA\Tag(name: 'Users', description: 'Управление пользователями'),
        new OA\Tag(name: 'Auth', description: 'Аутентификация и авторизация'),
        new OA\Tag(name: 'Templates', description: 'Управление шаблонами'),
        new OA\Tag(name: 'TVs', description: 'Управление TV-параметрами'),
        new OA\Tag(name: 'Elements', description: 'Управление элементами (чанки, сниппеты и т.д.)'),
        new OA\Tag(name: 'System', description: 'Системные настройки и логи'),
    ],
    security: [
        new OA\SecurityScheme(
            securityScheme: 'bearerAuth',
            type: 'http',
            scheme: 'bearer',
            bearerFormat: 'JWT'
        )
    ],
    externalDocs: new OA\ExternalDocumentation(
        description: 'Документация Evolution CMS',
        url: 'https://docs.evo-cms.com'
    )
)]
class OpenApi
{
}