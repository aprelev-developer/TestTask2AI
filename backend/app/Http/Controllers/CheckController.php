<?php

namespace App\Http\Controllers;

use App\Application\Checks\RunCheck;
use App\Domain\Checks\CheckInput;
use App\Domain\Checks\Exceptions\ReferencePaymentNotFound;
use App\Http\Requests\StoreCheckRequest;
use App\Http\Resources\CheckResultResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class CheckController extends Controller
{
    public function __construct(private readonly RunCheck $runCheck) {}

    #[OA\Post(
        path: '/api/checks',
        summary: 'Проверить платёжную страницу на подмену',
        description: 'Сравнивает наблюдения, снятые фронтендом с платёжной страницы, с эталонными данными '
            .'запуска (run_id) и возвращает результат по всем 5 сценариям подмены (SPEC.md §4, §7-9). '
            .'Утилита никогда не блокирует и не выполняет перевод сама — только показывает результат.',
        tags: ['Checks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['run_id', 'displayed_address', 'displayed_amount', 'displayed_network'],
                properties: [
                    new OA\Property(property: 'run_id', type: 'string', format: 'uuid', description: 'Идентификатор тестового запуска, под который есть эталонные данные платежа.'),
                    new OA\Property(property: 'displayed_address', type: 'string', description: 'Адрес, отображённый на странице.'),
                    new OA\Property(property: 'displayed_amount', type: 'string', description: 'Сумма, отображённая на странице (decimal-строка).', example: '0.015'),
                    new OA\Property(property: 'displayed_network', type: 'string', description: 'Сеть, отображённая на странице.'),
                    new OA\Property(property: 'qr_address', type: 'string', nullable: true, description: 'Адрес, декодированный из QR-кода.'),
                    new OA\Property(property: 'qr_amount', type: 'string', nullable: true, description: 'Сумма, декодированная из QR-кода (decimal-строка).'),
                    new OA\Property(property: 'qr_network', type: 'string', nullable: true, description: 'Сеть, декодированная из QR-кода.'),
                    new OA\Property(property: 'copy_button_value', type: 'string', nullable: true, description: 'Значение, скопированное нажатием кнопки «Копировать».'),
                    new OA\Property(property: 'address_after_watch_window', type: 'string', nullable: true, description: 'Адрес, зафиксированный через 5 секунд после старта проверки.'),
                    new OA\Property(
                        property: 'page_scripts',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'string'),
                        description: 'Список источников подключённых на странице <script>.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Проверка выполнена — результат по всем сценариям подмены.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'result',
                            type: 'string',
                            nullable: true,
                            enum: ['Подмена не обнаружена', 'Есть подозрение', 'Обнаружена подмена', null],
                            description: 'Итоговый статус проверки. null, если ничего не сработало, но '
                                .'проверка неполная — SPEC.md §8 запрещает в этом случае показывать '
                                .'«Подмена не обнаружена»; см. incomplete_message.'
                        ),
                        new OA\Property(
                            property: 'triggered_scenarios',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['7.1']
                        ),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'scenario', type: 'string', example: '7.1'),
                                    new OA\Property(property: 'expected', type: 'string', nullable: true),
                                    new OA\Property(property: 'actual', type: 'string', nullable: true),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'incomplete_checks',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            description: 'Сценарии, которые не удалось технически проверить.'
                        ),
                        new OA\Property(
                            property: 'incomplete_message',
                            type: 'string',
                            nullable: true,
                            example: 'Проверка выполнена не полностью',
                            description: 'SPEC.md §8: точный текст технического сообщения, когда '
                                .'incomplete_checks не пуст; null, если проверка полная.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Тело запроса не прошло валидацию.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'message', type: 'string'),
                                new OA\Property(property: 'fields', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'run_id синтаксически валиден, но эталонного платежа под него нет.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'message', type: 'string'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreCheckRequest $request): CheckResultResource|JsonResponse
    {
        $validated = $request->validated();

        $input = new CheckInput(
            displayedAddress: $validated['displayed_address'],
            displayedAmount: $validated['displayed_amount'],
            displayedNetwork: $validated['displayed_network'],
            qrAddress: $validated['qr_address'] ?? null,
            qrAmount: $validated['qr_amount'] ?? null,
            qrNetwork: $validated['qr_network'] ?? null,
            copyButtonValue: $validated['copy_button_value'] ?? null,
            addressAfterWatchWindow: $validated['address_after_watch_window'] ?? null,
            pageScripts: $validated['page_scripts'] ?? null,
        );

        try {
            $result = ($this->runCheck)($validated['run_id'], $input);
        } catch (ReferencePaymentNotFound $exception) {
            return response()->json([
                'error' => [
                    'message' => $exception->getMessage() ?: 'Эталонные данные платежа для указанного run_id не найдены.',
                ],
            ], 404);
        }

        return new CheckResultResource($result);
    }
}
