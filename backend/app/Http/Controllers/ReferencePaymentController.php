<?php

namespace App\Http\Controllers;

use App\Application\Checks\CreateReferencePayment;
use App\Domain\Checks\Exceptions\ReferencePaymentNotFound;
use App\Domain\Checks\Ports\ReferencePaymentRepository;
use App\Http\Requests\StoreReferencePaymentRequest;
use App\Http\Resources\ReferencePaymentResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Test-fixture endpoints: SPEC.md never says where a run's ground-truth
 * data comes from — these exist purely so the frontend can create/fetch it.
 * They carry no fraud-scenario logic and are not covered by
 * spec-compliance (see backend-conventions → Test-fixture endpoints).
 */
final class ReferencePaymentController extends Controller
{
    public function __construct(
        private readonly CreateReferencePayment $createReferencePayment,
        private readonly ReferencePaymentRepository $referencePayments,
    ) {}

    #[OA\Post(
        path: '/api/reference-payments',
        summary: 'Создать эталонные данные тестового запуска',
        description: 'Не часть SPEC.md — служебный эндпоинт для фронтенда, чтобы создать эталон платежа '
            .'под новый run_id. Все поля опциональны: любое непереданное поле заполняется '
            .'ReferencePaymentGenerator случайным, заведомо вымышленным значением.',
        tags: ['Reference payments'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'address', type: 'string', nullable: true, description: 'Эталонный адрес.'),
                    new OA\Property(property: 'amount', type: 'string', nullable: true, description: 'Эталонная сумма (decimal-строка).', example: '0.015'),
                    new OA\Property(property: 'network', type: 'string', nullable: true, description: 'Эталонная сеть.'),
                    new OA\Property(
                        property: 'allowed_scripts',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'string'),
                        description: 'Список разрешённых источников <script>.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Эталонные данные созданы.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'run_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'address', type: 'string'),
                        new OA\Property(property: 'amount', type: 'string', example: '0.015'),
                        new OA\Property(property: 'network', type: 'string'),
                        new OA\Property(
                            property: 'allowed_scripts',
                            type: 'array',
                            items: new OA\Items(type: 'string')
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
        ]
    )]
    public function store(StoreReferencePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payment = ($this->createReferencePayment)(
            $validated['address'] ?? null,
            $validated['amount'] ?? null,
            $validated['network'] ?? null,
            $validated['allowed_scripts'] ?? null,
        );

        return (new ReferencePaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/reference-payments/{run_id}',
        summary: 'Получить эталонные данные тестового запуска',
        description: 'Не часть SPEC.md — служебный эндпоинт для фронтенда, чтобы получить ранее '
            .'созданный эталон платежа по run_id.',
        tags: ['Reference payments'],
        parameters: [
            new OA\Parameter(
                name: 'run_id',
                description: 'Идентификатор тестового запуска.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Эталонные данные найдены.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'run_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'address', type: 'string'),
                        new OA\Property(property: 'amount', type: 'string', example: '0.015'),
                        new OA\Property(property: 'network', type: 'string'),
                        new OA\Property(
                            property: 'allowed_scripts',
                            type: 'array',
                            items: new OA\Items(type: 'string')
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
    public function show(string $runId): ReferencePaymentResource|JsonResponse
    {
        try {
            $payment = $this->referencePayments->findForRun($runId);
        } catch (ReferencePaymentNotFound $exception) {
            return response()->json([
                'error' => [
                    'message' => $exception->getMessage() ?: 'Эталонные данные платежа для указанного run_id не найдены.',
                ],
            ], 404);
        }

        return new ReferencePaymentResource($payment);
    }
}
