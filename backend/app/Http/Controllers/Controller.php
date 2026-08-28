<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'ScamTest Antifraud API',
    description: 'Проверяет платёжную страницу на подмену адреса/суммы/сети и на подключённые сторонние скрипты (SPEC.md §4, §7-9).'
)]
#[OA\Tag(name: 'Checks', description: 'Проверка платёжной страницы на признаки подмены')]
abstract class Controller
{
    //
}
