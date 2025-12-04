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
    externalDocs: new OA\ExternalDocumentation(
        description: 'Документация Evolution CMS',
        url: 'https://docs.evo-cms.com'
    )
)]
class OpenApi
{
}