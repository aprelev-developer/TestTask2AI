# ScamTest Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Создать тестовую криптоплатёжную форму React, которая получает и редактирует реквизиты, стабильно симулирует один из пяти антифрод-сценариев, вызывает существующий Laravel API и показывает чистый результат зелёной панелью, а остальные результаты — модально.

**Architecture:** Одностраничное Vite-приложение разделяется на API-клиент, чистую доменную логику сценариев/наблюдений, orchestration-hook и небольшие React-компоненты. Источник сценария скрыт за `ScenarioSource`, поэтому локальный детерминированный выбор позднее заменяется backend API без изменения UI. Все функциональные изменения выполняются циклом red-green-refactor.

**Tech Stack:** React 19, TypeScript, Vite 8, Vitest 4, React Testing Library, jsdom, `qrcode.react`, CSS без UI-фреймворка.

**Spec:** `docs/superpowers/specs/2026-08-29-scamtest-frontend-design.md`

## Global Constraints

- Использовать существующие `POST /api/reference-payments` и `POST /api/checks`; backend не менять.
- Кнопка называется `ОТПРАВИТЬ`, но настоящая транзакция никогда не выполняется.
- Адрес и сумма редактируются; сеть выбирается только из `BTC`, `ETH`, `TRX`.
- Один `run_id` всегда соответствует одному сценарию; повтор без изменений воспроизводит результат.
- Чистый ответ показывается зелёной панелью под QR без модального окна.
- Подозрение, подмена и техническая неполнота показываются модально.
- Никаких вкладок состояний, router, глобального state manager, UI-kit и внешних шрифтов.
- Суммы передаются API только строками.
- Тексты `result` и `incomplete_message` выводятся дословно из backend.
- В PowerShell использовать `npm.cmd`, а не заблокированный `npm.ps1`.

---

## File Map

```text
frontend/
├── public/untrusted-demo.js                 # безопасный локальный маркер сценария 7.5
├── src/api/scamtestApi.ts                   # HTTP-контракт и ApiError
├── src/api/scamtestApi.test.ts              # контракт запросов/ошибок
├── src/components/CleanStatusPanel.tsx      # зелёный/нейтральный индикатор
├── src/components/PaymentForm.tsx           # поля и кнопки
├── src/components/QrPreview.tsx             # QR из единого payload
├── src/components/ResultDialog.tsx          # modal для non-clean результатов
├── src/components/ServiceMessage.tsx        # ошибки загрузки/проверки
├── src/domain/observations.ts               # QR payload, fingerprint, CheckRequest
├── src/domain/observations.test.ts           # чистые и изменённые наблюдения
├── src/domain/scenario.ts                   # ScenarioSource и stable hash
├── src/domain/scenario.test.ts              # стабильность/достижимость
├── src/domain/types.ts                      # API/domain типы
├── src/hooks/usePaymentSimulation.ts        # последовательность загрузки/проверки
├── src/hooks/usePaymentSimulation.test.tsx  # таймер, reuse run_id, state cleanup
├── src/test/setup.ts                        # jest-dom и cleanup
├── src/App.tsx                              # композиция экрана
├── src/App.test.tsx                         # пользовательский поток и presentation
├── src/main.tsx                             # React entrypoint
├── src/styles.css                           # Runet 2000 visual system
├── index.html
├── package.json
├── tsconfig.json
└── vite.config.ts                           # React, Vitest, proxy /api
```

---

### Task 1: Vite + React + TypeScript test harness

**Files:**
- Create: `frontend/` through the official `react-ts` Vite template
- Modify: `frontend/package.json`
- Modify: `frontend/vite.config.ts`
- Create: `frontend/src/test/setup.ts`
- Test: `frontend/src/App.test.tsx`
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: Node.js `v24.20.0`, npm `11.19.0`.
- Produces: `npm.cmd test -- --run`, `npm.cmd run build`, jsdom test environment, `/api` development proxy.

- [ ] **Step 1: Create the generated TypeScript shell and install only approved dependencies**

Run from repository root:

```powershell
npm.cmd create vite@latest frontend -- --template react-ts
cd frontend
npm.cmd install
npm.cmd install qrcode.react
npm.cmd install --save-dev vitest jsdom @testing-library/react @testing-library/dom @testing-library/jest-dom @testing-library/user-event
```

Expected: `frontend/package.json` contains React/TypeScript/Vite plus the listed testing and QR packages; no router or UI framework appears.

- [ ] **Step 2: Configure Vitest and the backend proxy**

Replace `frontend/vite.config.ts` with:

```ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    css: true,
  },
})
```

Create `frontend/src/test/setup.ts`:

```ts
import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

afterEach(cleanup)
```

Add package scripts:

```json
{
  "scripts": {
    "dev": "vite",
    "build": "tsc -b && vite build",
    "test": "vitest",
    "preview": "vite preview"
  }
}
```

- [ ] **Step 3: Write the first failing user-visible test**

Replace `frontend/src/App.test.tsx` with:

```tsx
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import App from './App'

describe('App', () => {
  it('identifies itself as a non-transactional simulator', () => {
    render(<App />)

    expect(screen.getByRole('heading', { name: 'АНТИФРОД 2000' })).toBeVisible()
    expect(screen.getByText(/настоящая транзакция не выполняется/i)).toBeVisible()
  })
})
```

- [ ] **Step 4: Run the test and confirm the expected failure**

Run:

```powershell
npm.cmd test -- --run src/App.test.tsx
```

Expected: FAIL because the generated Vite `App` does not contain `АНТИФРОД 2000` or the simulator disclaimer.

- [ ] **Step 5: Implement the smallest application shell**

Replace `frontend/src/App.tsx` with:

```tsx
export default function App() {
  return (
    <main>
      <h1>АНТИФРОД 2000</h1>
      <p>СИМУЛЯТОР — НАСТОЯЩАЯ ТРАНЗАКЦИЯ НЕ ВЫПОЛНЯЕТСЯ</p>
    </main>
  )
}
```

- [ ] **Step 6: Verify green and build**

Run:

```powershell
npm.cmd test -- --run
npm.cmd run build
```

Expected: one test passes; production build exits with code 0.

- [ ] **Step 7: Commit the harness**

```powershell
git add frontend
git commit -m "chore: scaffold frontend test harness"
```

---

### Task 2: Typed backend API client

**Files:**
- Create: `frontend/src/domain/types.ts`
- Create: `frontend/src/api/scamtestApi.test.ts`
- Create: `frontend/src/api/scamtestApi.ts`

**Interfaces:**
- Consumes: relative `/api` proxy from Task 1.
- Produces: `createScamtestApi(fetchImpl)`, `createReferencePayment(input, signal?)`, `runCheck(input, signal?)`, `ApiError`.

- [ ] **Step 1: Define exact shared types**

Create `frontend/src/domain/types.ts`:

```ts
export type Network = 'BTC' | 'ETH' | 'TRX'

export interface PaymentValues {
  address: string
  amount: string
  network: Network
}

export interface ReferencePayment extends PaymentValues {
  run_id: string
  allowed_scripts: string[]
}

export interface CreateReferencePaymentInput {
  address?: string
  amount?: string
  network?: Network
  allowed_scripts: string[]
}

export interface CheckRequest {
  run_id: string
  displayed_address: string
  displayed_amount: string
  displayed_network: string
  qr_address: string | null
  qr_amount: string | null
  qr_network: string | null
  copy_button_value: string | null
  address_after_watch_window: string | null
  page_scripts: string[] | null
}

export interface CheckDetail {
  scenario: string
  expected: unknown
  actual: unknown
}

export interface CheckResult {
  result: 'Подмена не обнаружена' | 'Есть подозрение' | 'Обнаружена подмена' | null
  triggered_scenarios: string[]
  details: CheckDetail[]
  incomplete_checks: string[]
  incomplete_message: 'Проверка выполнена не полностью' | null
}
```

- [ ] **Step 2: Write failing HTTP contract tests**

Create `frontend/src/api/scamtestApi.test.ts` with a tiny injected fetch double:

```ts
import { describe, expect, it, vi } from 'vitest'
import { ApiError, createScamtestApi } from './scamtestApi'

const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })

describe('scamtestApi', () => {
  it('creates a reference payment with a JSON POST', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({
      run_id: '11111111-1111-1111-1111-111111111111',
      address: 'test-address', amount: '1.0', network: 'BTC', allowed_scripts: [],
    }, 201))
    const api = createScamtestApi(fetchImpl)

    const result = await api.createReferencePayment({ allowed_scripts: [] })

    expect(result.address).toBe('test-address')
    expect(fetchImpl).toHaveBeenCalledWith('/api/reference-payments', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ allowed_scripts: [] }),
    }))
  })

  it('preserves the backend error message and fields', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({
      error: { message: 'Переданные данные не прошли проверку.', fields: { amount: ['Неверная сумма'] } },
    }, 422))
    const api = createScamtestApi(fetchImpl)

    await expect(api.createReferencePayment({ amount: 'abc', allowed_scripts: [] }))
      .rejects.toEqual(new ApiError('Переданные данные не прошли проверку.', 422, { amount: ['Неверная сумма'] }))
  })
})
```

- [ ] **Step 3: Run and confirm red**

```powershell
npm.cmd test -- --run src/api/scamtestApi.test.ts
```

Expected: FAIL because `scamtestApi.ts` does not exist.

- [ ] **Step 4: Implement the minimal client**

Create `frontend/src/api/scamtestApi.ts`:

```ts
import type { CheckRequest, CheckResult, CreateReferencePaymentInput, ReferencePayment } from '../domain/types'

type FetchLike = typeof fetch

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number | null,
    public readonly fields: Record<string, string[]> = {},
  ) {
    super(message)
  }
}

export function createScamtestApi(fetchImpl: FetchLike = fetch) {
  async function post<T>(url: string, body: unknown, signal?: AbortSignal): Promise<T> {
    let response: Response
    try {
      response = await fetchImpl(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        signal,
      })
    } catch {
      throw new ApiError('Сервис проверки временно недоступен', null)
    }
    const payload = await response.json()
    if (!response.ok) {
      throw new ApiError(
        payload?.error?.message ?? 'Сервис проверки временно недоступен',
        response.status,
        payload?.error?.fields ?? {},
      )
    }
    return payload as T
  }

  return {
    createReferencePayment: (input: CreateReferencePaymentInput, signal?: AbortSignal) =>
      post<ReferencePayment>('/api/reference-payments', input, signal),
    runCheck: (input: CheckRequest, signal?: AbortSignal) =>
      post<CheckResult>('/api/checks', input, signal),
  }
}
```

- [ ] **Step 5: Add a `runCheck` test and verify green**

Append this exact contract test:

```ts
it('posts raw observations to the checks endpoint', async () => {
  const responseBody = {
    result: 'Подмена не обнаружена',
    triggered_scenarios: [], details: [], incomplete_checks: [], incomplete_message: null,
  }
  const fetchImpl = vi.fn().mockResolvedValue(jsonResponse(responseBody))
  const api = createScamtestApi(fetchImpl)
  const request = {
    run_id: '11111111-1111-1111-1111-111111111111',
    displayed_address: 'test-address', displayed_amount: '1.0', displayed_network: 'BTC',
    qr_address: 'test-address', qr_amount: '1.0', qr_network: 'BTC',
    copy_button_value: 'test-address', address_after_watch_window: 'test-address',
    page_scripts: [],
  }

  await expect(api.runCheck(request)).resolves.toEqual(responseBody)
  expect(fetchImpl).toHaveBeenCalledWith('/api/checks', expect.objectContaining({
    method: 'POST', body: JSON.stringify(request),
  }))
})
```

Then run:

```powershell
npm.cmd test -- --run src/api/scamtestApi.test.ts
```

Expected: all API client tests pass.

- [ ] **Step 6: Commit**

```powershell
git add frontend/src/api frontend/src/domain/types.ts
git commit -m "feat: add typed scamtest api client"
```

---

### Task 3: Stable scenarios and raw observations

**Files:**
- Create: `frontend/src/domain/scenario.test.ts`
- Create: `frontend/src/domain/scenario.ts`
- Create: `frontend/src/domain/observations.test.ts`
- Create: `frontend/src/domain/observations.ts`
- Create: `frontend/public/untrusted-demo.js`

**Interfaces:**
- Consumes: `PaymentValues`, `ReferencePayment`, `CheckRequest`.
- Produces: `FraudScenario`, `ScenarioSource`, `deterministicScenarioSource`, `paymentFingerprint`, `createQrPayload`, `createScenarioObservations`, `buildCheckRequest`.

- [ ] **Step 1: Write failing stable-scenario tests**

Create `frontend/src/domain/scenario.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { deterministicScenarioSource } from './scenario'

describe('deterministicScenarioSource', () => {
  it('returns the same scenario for the same run id', () => {
    const runId = '11111111-1111-1111-1111-111111111111'
    expect(deterministicScenarioSource.forRun(runId))
      .toEqual(deterministicScenarioSource.forRun(runId))
  })

  it('can produce clean, suspicion, and tampering in a representative sample', () => {
    const kinds = new Set(
      Array.from({ length: 300 }, (_, index) =>
        deterministicScenarioSource.forRun(`run-${index}`).kind),
    )
    expect(kinds).toEqual(new Set(['clean', 'suspicion', 'tampering']))
  })
})
```

- [ ] **Step 2: Verify red**

```powershell
npm.cmd test -- --run src/domain/scenario.test.ts
```

Expected: FAIL because `scenario.ts` does not exist.

- [ ] **Step 3: Implement a stable FNV-1a based source**

Create `frontend/src/domain/scenario.ts`:

```ts
export type TamperingVariant = '7.1' | '7.2' | '7.3' | '7.4'
export type FraudScenario =
  | { kind: 'clean' }
  | { kind: 'suspicion'; scenario: '7.5' }
  | { kind: 'tampering'; scenario: TamperingVariant }

export interface ScenarioSource {
  forRun(runId: string): FraudScenario
}

function fnv1a(value: string): number {
  let hash = 0x811c9dc5
  for (let index = 0; index < value.length; index += 1) {
    hash ^= value.charCodeAt(index)
    hash = Math.imul(hash, 0x01000193)
  }
  return hash >>> 0
}

export const deterministicScenarioSource: ScenarioSource = {
  forRun(runId) {
    const hash = fnv1a(runId)
    const kind = hash % 3
    if (kind === 0) return { kind: 'clean' }
    if (kind === 1) return { kind: 'suspicion', scenario: '7.5' }
    const variants: TamperingVariant[] = ['7.1', '7.2', '7.3', '7.4']
    return { kind: 'tampering', scenario: variants[Math.floor(hash / 3) % variants.length] }
  },
}
```

- [ ] **Step 4: Run scenario tests green**

```powershell
npm.cmd test -- --run src/domain/scenario.test.ts
```

Expected: both tests pass.

- [ ] **Step 5: Write failing observation tests**

Create `frontend/src/domain/observations.test.ts` with the complete focused matrix:

```ts
import { describe, expect, it } from 'vitest'
import { buildCheckRequest, createQrPayload, createScenarioObservations, paymentFingerprint } from './observations'

const reference = {
  run_id: '11111111-1111-1111-1111-111111111111',
  address: 'test-address', amount: '1.00000000', network: 'BTC' as const,
  allowed_scripts: ['http://localhost:5173/src/main.tsx'],
}

describe('observations', () => {
  it('uses one serialization for QR payload', () => {
    expect(createQrPayload(reference)).toBe(
      '{"address":"test-address","amount":"1.00000000","network":"BTC"}',
    )
  })

  it('fingerprints all editable values', () => {
    expect(paymentFingerprint(reference)).not.toBe(
      paymentFingerprint({ ...reference, amount: '2.00000000' }),
    )
  })

  it('builds complete matching observations for a clean run', () => {
    expect(buildCheckRequest(reference, { kind: 'clean' }, reference.allowed_scripts))
      .toMatchObject({
        run_id: reference.run_id,
        displayed_address: 'test-address',
        displayed_amount: '1.00000000',
        displayed_network: 'BTC',
        qr_address: 'test-address',
        qr_amount: '1.00000000',
        qr_network: 'BTC',
        copy_button_value: 'test-address',
        address_after_watch_window: 'test-address',
        page_scripts: reference.allowed_scripts,
      })
  })

  it.each([
    ['7.1', 'qr_address'],
    ['7.2', 'copy_button_value'],
    ['7.3', 'address_after_watch_window'],
    ['7.4', 'qr_amount'],
  ] as const)('changes only the intended observation for scenario %s', (scenario, field) => {
    const clean = buildCheckRequest(reference, { kind: 'clean' }, reference.allowed_scripts)
    const tampered = buildCheckRequest(reference, { kind: 'tampering', scenario }, reference.allowed_scripts)
    const changed = Object.keys(clean).filter((key) =>
      clean[key as keyof typeof clean] !== tampered[key as keyof typeof tampered])
    expect(changed).toEqual([field])
  })

  it('passes the actually observed extra script for scenario 7.5', () => {
    const scripts = [...reference.allowed_scripts, 'http://localhost:5173/untrusted-demo.js']
    expect(buildCheckRequest(reference, { kind: 'suspicion', scenario: '7.5' }, scripts).page_scripts)
      .toEqual(scripts)
  })
})
```

- [ ] **Step 6: Verify red, implement transformations, then verify green**

Run red:

```powershell
npm.cmd test -- --run src/domain/observations.test.ts
```

Create `frontend/src/domain/observations.ts`:

```ts
import type { CheckRequest, PaymentValues, ReferencePayment } from './types'
import type { FraudScenario } from './scenario'

export function createQrPayload(values: PaymentValues): string {
  return JSON.stringify({
    address: values.address,
    amount: values.amount,
    network: values.network,
  })
}

export function paymentFingerprint(values: PaymentValues): string {
  return createQrPayload(values)
}

export function substitutedAddress(address: string): string {
  return `${address}-demo-substitution`
}

export interface ScenarioObservations {
  qrValues: PaymentValues
  copyButtonValue: string
  addressAfterWatchWindow: string
}

export function createScenarioObservations(
  reference: ReferencePayment,
  scenario: FraudScenario,
): ScenarioObservations {
  const observations: ScenarioObservations = {
    qrValues: { address: reference.address, amount: reference.amount, network: reference.network },
    copyButtonValue: reference.address,
    addressAfterWatchWindow: reference.address,
  }
  if (scenario.kind !== 'tampering') return observations
  if (scenario.scenario === '7.1') observations.qrValues.address = substitutedAddress(reference.address)
  if (scenario.scenario === '7.2') observations.copyButtonValue = substitutedAddress(reference.address)
  if (scenario.scenario === '7.3') observations.addressAfterWatchWindow = substitutedAddress(reference.address)
  if (scenario.scenario === '7.4') observations.qrValues.amount = substitutedAmount(reference.amount)
  return observations
}

function substitutedAmount(amount: string): string {
  return amount === '999.99999999' ? '998.99999999' : '999.99999999'
}

export function buildCheckRequest(
  reference: ReferencePayment,
  scenario: FraudScenario,
  observedScripts: string[],
): CheckRequest {
  const observations = createScenarioObservations(reference, scenario)
  const request: CheckRequest = {
    run_id: reference.run_id,
    displayed_address: reference.address,
    displayed_amount: reference.amount,
    displayed_network: reference.network,
    qr_address: observations.qrValues.address,
    qr_amount: observations.qrValues.amount,
    qr_network: observations.qrValues.network,
    copy_button_value: observations.copyButtonValue,
    address_after_watch_window: observations.addressAfterWatchWindow,
    page_scripts: observedScripts,
  }
  return request
}
```

Then rerun:

```powershell
npm.cmd test -- --run src/domain/observations.test.ts
```

Expected: clean plus all five scenario tests pass.

- [ ] **Step 7: Add the inert demo script and commit**

Create `frontend/public/untrusted-demo.js` containing only:

```js
// Intentionally inert local fixture for ScamTest scenario 7.5.
```

```powershell
git add frontend/src/domain frontend/public/untrusted-demo.js
git commit -m "feat: model stable antifraud scenarios"
```

---

### Task 4: Payment simulation orchestration hook

**Files:**
- Create: `frontend/src/hooks/usePaymentSimulation.test.tsx`
- Create: `frontend/src/hooks/usePaymentSimulation.ts`

**Interfaces:**
- Consumes: `createScamtestApi`, `ScenarioSource`, `buildCheckRequest`, `paymentFingerprint`.
- Produces: `usePaymentSimulation(dependencies?)` with `status`, `formValues`, `reference`, `result`, `error`, `fieldErrors`, `setField`, `refresh`, `submit`, `dismissResult`, `retry`.

- [ ] **Step 1: Define dependency-injected orchestration tests**

Create the file with this explicit setup before the three behavior tests:

```tsx
import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { usePaymentSimulation } from './usePaymentSimulation'

const reference = {
  run_id: '11111111-1111-1111-1111-111111111111',
  address: 'test-address', amount: '1.0', network: 'BTC' as const,
  allowed_scripts: ['http://localhost:5173/src/main.tsx'],
}
const cleanResult = {
  result: 'Подмена не обнаружена' as const,
  triggered_scenarios: [], details: [], incomplete_checks: [], incomplete_message: null,
}
const api = {
  createReferencePayment: vi.fn().mockResolvedValue(reference),
  runCheck: vi.fn().mockResolvedValue(cleanResult),
}
const dependencies = {
  api,
  scenarioSource: { forRun: () => ({ kind: 'clean' as const }) },
  wait: () => Promise.resolve(),
  getPageScripts: () => reference.allowed_scripts,
  attachDemoScript: () => () => undefined,
}

beforeEach(() => {
  api.createReferencePayment.mockClear()
  api.runCheck.mockClear()
})

describe('usePaymentSimulation', () => {
it('loads generated values and starts ready', async () => {
  const { result } = renderHook(() => usePaymentSimulation(dependencies))
  await waitFor(() => expect(result.current.status).toBe('ready'))
  expect(result.current.formValues).toEqual({ address: 'test-address', amount: '1.0', network: 'BTC' })
})

it('reuses the run id when values did not change', async () => {
  const { result } = renderHook(() => usePaymentSimulation(dependencies))
  await waitFor(() => expect(result.current.status).toBe('ready'))
  await act(async () => result.current.submit())
  await act(async () => result.current.submit())
  expect(api.createReferencePayment).toHaveBeenCalledTimes(1)
  expect(api.runCheck).toHaveBeenCalledTimes(2)
})

it('creates a new reference after editing', async () => {
  const { result } = renderHook(() => usePaymentSimulation(dependencies))
  await waitFor(() => expect(result.current.status).toBe('ready'))
  act(() => result.current.setField('amount', '2.0'))
  await act(async () => result.current.submit())
  expect(api.createReferencePayment).toHaveBeenLastCalledWith(expect.objectContaining({ amount: '2.0' }), expect.anything())
})
})
```

- [ ] **Step 2: Verify red**

```powershell
npm.cmd test -- --run src/hooks/usePaymentSimulation.test.tsx
```

Expected: FAIL because the hook is missing.

- [ ] **Step 3: Implement the minimal hook state machine**

Use this public contract:

```ts
export type SimulationStatus =
  | 'loadingReference' | 'ready' | 'refreshing' | 'checking'
  | 'cleanResult' | 'result' | 'referenceError' | 'checkError'

export interface SimulationDependencies {
  api: ReturnType<typeof createScamtestApi>
  scenarioSource: ScenarioSource
  wait: (milliseconds: number) => Promise<void>
  getPageScripts: () => string[]
  attachDemoScript: () => () => void
}
```

Implement `usePaymentSimulation` around the following complete state flow
(imports come from Tasks 2–3):

```ts
const defaultDependencies: SimulationDependencies = {
  api: createScamtestApi(),
  scenarioSource: deterministicScenarioSource,
  wait: (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds)),
  getPageScripts: () => Array.from(document.scripts).map((script) => script.src).filter(Boolean),
  attachDemoScript: () => {
    const script = document.createElement('script')
    script.src = '/untrusted-demo.js'
    document.body.append(script)
    return () => script.remove()
  },
}

export function usePaymentSimulation(dependencies: SimulationDependencies = defaultDependencies) {
  const { api, scenarioSource, wait, getPageScripts, attachDemoScript } = dependencies
  const [status, setStatus] = useState<SimulationStatus>('loadingReference')
  const [formValues, setFormValues] = useState<PaymentValues>({ address: '', amount: '', network: 'BTC' })
  const [qrValues, setQrValues] = useState<PaymentValues>({ address: '', amount: '', network: 'BTC' })
  const [copyButtonValue, setCopyButtonValue] = useState('')
  const [reference, setReference] = useState<ReferencePayment | null>(null)
  const [referenceFingerprint, setReferenceFingerprint] = useState<string | null>(null)
  const [result, setResult] = useState<CheckResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const busy = useRef(false)
  const mounted = useRef(true)
  const controller = useRef<AbortController | null>(null)

  function beginRequest(): AbortSignal {
    controller.current?.abort()
    controller.current = new AbortController()
    return controller.current.signal
  }

  const refresh = useCallback(async () => {
    if (busy.current) return
    busy.current = true
    setStatus(reference ? 'refreshing' : 'loadingReference')
    setError(null)
    try {
      const created = await api.createReferencePayment(
        { allowed_scripts: getPageScripts() },
        beginRequest(),
      )
      if (!mounted.current) return
      const values = { address: created.address, amount: created.amount, network: created.network }
      const scenario = scenarioSource.forRun(created.run_id)
      const observations = createScenarioObservations(created, scenario)
      setReference(created)
      setFormValues(values)
      setQrValues(observations.qrValues)
      setCopyButtonValue(observations.copyButtonValue)
      setReferenceFingerprint(paymentFingerprint(values))
      setResult(null)
      setStatus('ready')
    } catch (caught) {
      if (!mounted.current) return
      setError(caught instanceof Error ? caught.message : 'Сервис проверки временно недоступен')
      setStatus('referenceError')
    } finally {
      busy.current = false
    }
  }, [api, getPageScripts, reference])

  useEffect(() => {
    mounted.current = true
    void refresh()
    return () => {
      mounted.current = false
      controller.current?.abort()
    }
  }, [])

  function setField<K extends keyof PaymentValues>(field: K, value: PaymentValues[K]) {
    setFormValues((current) => {
      const updated = { ...current, [field]: value }
      setQrValues(updated)
      setCopyButtonValue(updated.address)
      return updated
    })
    setResult(null)
    setStatus('ready')
    setFieldErrors({})
  }

  async function submit() {
    if (busy.current || !reference) return
    const validation: Record<string, string[]> = {}
    if (!formValues.address.trim()) validation.address = ['Укажите адрес получателя']
    if (!/^\d+(\.\d+)?$/.test(formValues.amount)) validation.amount = ['Укажите сумму числом']
    if (Object.keys(validation).length > 0) {
      setFieldErrors(validation)
      return
    }

    busy.current = true
    setStatus('checking')
    setError(null)
    let cleanupDemoScript = () => undefined
    const originalValues = formValues
    const signal = beginRequest()
    try {
      let activeReference = reference
      if (paymentFingerprint(formValues) !== referenceFingerprint) {
        activeReference = await api.createReferencePayment({
          ...formValues,
          allowed_scripts: getPageScripts(),
        }, signal)
        setReference(activeReference)
        setReferenceFingerprint(paymentFingerprint(formValues))
      }
      const scenario = scenarioSource.forRun(activeReference.run_id)
      const observations = createScenarioObservations(activeReference, scenario)
      setQrValues(observations.qrValues)
      setCopyButtonValue(observations.copyButtonValue)
      if (scenario.kind === 'suspicion') cleanupDemoScript = attachDemoScript()
      if (scenario.kind === 'tampering' && scenario.scenario === '7.3') {
        setFormValues({ ...formValues, address: substitutedAddress(formValues.address) })
      }
      await wait(5000)
      const response = await api.runCheck(
        buildCheckRequest(activeReference, scenario, getPageScripts()),
        signal,
      )
      if (!mounted.current) return
      setResult(response)
      setStatus(response.result === 'Подмена не обнаружена' ? 'cleanResult' : 'result')
    } catch (caught) {
      if (!mounted.current) return
      const apiError = caught instanceof ApiError ? caught : null
      setFieldErrors(apiError?.fields ?? {})
      setError(apiError?.message ?? 'Сервис проверки временно недоступен')
      setResult(null)
      setStatus('checkError')
    } finally {
      cleanupDemoScript()
      if (mounted.current) setFormValues(originalValues)
      busy.current = false
    }
  }

  function dismissResult() {
    setResult(null)
    setStatus('ready')
  }

  return {
    status, formValues, qrValues, copyButtonValue, reference, result, error, fieldErrors,
    setField, refresh, submit, dismissResult,
    retry: status === 'referenceError' ? refresh : submit,
  }
}
```

Default `wait` is a Promise around `setTimeout`. `submit` must:

1. reject concurrent calls while `checking`;
2. validate address, decimal amount, and network;
3. create a new reference only when `paymentFingerprint` changed;
4. resolve the scenario from the active `run_id`;
5. attach the 7.5 fixture only for suspicion;
6. wait exactly `5000` milliseconds;
7. send `buildCheckRequest` to the API;
8. set `cleanResult` only for exact clean output, otherwise `result`;
9. always remove the fixture and restore the visible 7.3 address in `finally`.

- [ ] **Step 4: Add timer, clean presentation, and cleanup tests**

Append these exact tests (use fresh dependency objects per test):

```tsx
it('waits the complete five-second watch window', async () => {
  vi.useFakeTimers()
  const timedDependencies = {
    ...dependencies,
    wait: (milliseconds: number) => new Promise<void>((resolve) => setTimeout(resolve, milliseconds)),
  }
  const { result } = renderHook(() => usePaymentSimulation(timedDependencies))
  await act(async () => Promise.resolve())
  const submission = act(async () => result.current.submit())
  await vi.advanceTimersByTimeAsync(4999)
  expect(api.runCheck).not.toHaveBeenCalled()
  await vi.advanceTimersByTimeAsync(1)
  await submission
  expect(api.runCheck).toHaveBeenCalledTimes(1)
  vi.useRealTimers()
})

it('uses the side-panel state for a clean response and clears it on edit', async () => {
  const { result } = renderHook(() => usePaymentSimulation(dependencies))
  await waitFor(() => expect(result.current.status).toBe('ready'))
  await act(async () => result.current.submit())
  expect(result.current.status).toBe('cleanResult')
  act(() => result.current.setField('amount', '2.0'))
  expect(result.current.status).toBe('ready')
  expect(result.current.result).toBeNull()
})

it('shows a service error without inventing a result', async () => {
  api.runCheck.mockRejectedValueOnce(new ApiError('Сервис проверки временно недоступен', null))
  const { result } = renderHook(() => usePaymentSimulation(dependencies))
  await waitFor(() => expect(result.current.status).toBe('ready'))
  await act(async () => result.current.submit())
  expect(result.current.status).toBe('checkError')
  expect(result.current.result).toBeNull()
  expect(result.current.error).toBe('Сервис проверки временно недоступен')
})
```

- [ ] **Step 5: Verify hook green**

```powershell
npm.cmd test -- --run src/hooks/usePaymentSimulation.test.tsx
```

Expected: all hook tests pass without real five-second waits.

- [ ] **Step 6: Commit**

```powershell
git add frontend/src/hooks
git commit -m "feat: orchestrate payment simulation flow"
```

---

### Task 5: Accessible payment components and result presentation

**Files:**
- Create: `frontend/src/components/PaymentForm.tsx`
- Create: `frontend/src/components/QrPreview.tsx`
- Create: `frontend/src/components/CleanStatusPanel.tsx`
- Create: `frontend/src/components/ResultDialog.tsx`
- Create: `frontend/src/components/ServiceMessage.tsx`
- Modify: `frontend/src/App.test.tsx`
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: the state/actions from `usePaymentSimulation`.
- Produces: one accessible screen with no state tabs; clean panel under QR; modal only for suspicion, tampering, or incomplete result.

- [ ] **Step 1: Write failing clean-result presentation test**

Inject a hook dependency or API fixture into `App` and assert:

```tsx
it('shows a clean result beside the QR without opening a dialog', async () => {
  render(<App dependencies={cleanDependencies} />)
  await userEvent.click(await screen.findByRole('button', { name: 'ОТПРАВИТЬ' }))
  await vi.advanceTimersByTimeAsync(5000)

  expect(screen.getByText('Подмена не обнаружена')).toBeVisible()
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
})
```

- [ ] **Step 2: Write failing modal tests**

Append these focused `ResultDialog` tests:

```tsx
it.each(['Есть подозрение', 'Обнаружена подмена'] as const)(
  'opens a dialog for %s and exposes backend details',
  (status) => {
    render(<ResultDialog result={{
      result: status,
      triggered_scenarios: ['7.5'],
      details: [{ scenario: '7.5', expected: ['allowed.js'], actual: ['unknown.js'] }],
      incomplete_checks: [], incomplete_message: null,
    }} onClose={vi.fn()} />)
    const dialog = screen.getByRole('dialog')
    expect(dialog).toHaveTextContent(status)
    expect(dialog).toHaveTextContent('7.5')
    expect(dialog).toHaveTextContent('unknown.js')
  },
)

it('uses the incomplete message as the heading when result is null', () => {
  render(<ResultDialog result={{
    result: null, triggered_scenarios: [], details: [],
    incomplete_checks: ['7.1'], incomplete_message: 'Проверка выполнена не полностью',
  }} onClose={vi.fn()} />)
  expect(screen.getByRole('dialog')).toHaveTextContent('Проверка выполнена не полностью')
  expect(screen.queryByText('Подмена не обнаружена')).not.toBeInTheDocument()
})
```

- [ ] **Step 3: Verify red**

```powershell
npm.cmd test -- --run src/App.test.tsx
```

Expected: FAIL because form, QR, panel, and dialog components are missing.

- [ ] **Step 4: Implement focused component contracts**

Use these prop boundaries:

```ts
interface PaymentFormProps {
  values: PaymentValues
  disabled: boolean
  checking: boolean
  fieldErrors: Record<string, string[]>
  onChange: <K extends keyof PaymentValues>(field: K, value: PaymentValues[K]) => void
  onCopy: () => Promise<void>
  onRefresh: () => Promise<void>
  onSubmit: () => Promise<void>
}

interface CleanStatusPanelProps {
  clean: boolean
}

interface ResultDialogProps {
  result: CheckResult
  onClose: () => void
}
```

`QrPreview` receives a `PaymentValues`, calls `createQrPayload`, and renders
`QRCodeSVG` with a visible text summary. `ResultDialog` returns `null` for an
exact clean result as a defensive boundary even though `App` also excludes it.

Implement the two result components exactly around the backend response:

```tsx
export function CleanStatusPanel({ clean }: CleanStatusPanelProps) {
  return (
    <section className={`clean-status ${clean ? 'clean-status--verified' : ''}`} aria-live="polite">
      {clean ? '✓ Подмена не обнаружена' : 'Проверка ещё не выполнена'}
    </section>
  )
}

export function ResultDialog({ result, onClose }: ResultDialogProps) {
  const dialogRef = useRef<HTMLElement>(null)
  const previousFocus = useRef<HTMLElement | null>(document.activeElement as HTMLElement | null)
  useEffect(() => {
    dialogRef.current?.focus()
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', closeOnEscape)
    return () => {
      document.removeEventListener('keydown', closeOnEscape)
      previousFocus.current?.focus()
    }
  }, [onClose])
  if (result.result === 'Подмена не обнаружена') return null
  const heading = result.result ?? result.incomplete_message ?? 'Результат проверки'
  return (
    <div className="dialog-backdrop">
      <section ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="result-heading" tabIndex={-1}>
        <h2 id="result-heading">{heading}</h2>
        {result.details.map((detail) => (
          <article key={`${detail.scenario}-${JSON.stringify(detail.actual)}`}>
            <h3>Сценарий {detail.scenario}</h3>
            <p>Ожидалось: {JSON.stringify(detail.expected)}</p>
            <p>Обнаружено: {JSON.stringify(detail.actual)}</p>
          </article>
        ))}
        {result.incomplete_message && <p>{result.incomplete_message}</p>}
        {result.incomplete_checks.length > 0 && <p>Не завершены: {result.incomplete_checks.join(', ')}</p>}
        <p>Настоящая транзакция не выполнялась.</p>
        <button type="button" onClick={onClose}>ЗАКРЫТЬ</button>
      </section>
    </div>
  )
}
```

Implement `QrPreview` with the same payload used by observations:

```tsx
export function QrPreview({ values }: { values: PaymentValues }) {
  return (
    <section className="qr-preview" aria-label="QR-код платежа">
      <QRCodeSVG value={createQrPayload(values)} size={208} marginSize={2} />
      <p>{values.network} · {values.amount}</p>
      <p className="address-summary">{values.address}</p>
    </section>
  )
}
```

Implement the form and service error without hidden business logic:

```tsx
export function PaymentForm(props: PaymentFormProps) {
  const { values, disabled, checking, fieldErrors, onChange, onCopy, onRefresh, onSubmit } = props
  return (
    <form onSubmit={(event) => { event.preventDefault(); void onSubmit() }}>
      <label htmlFor="address">КОШЕЛЁК ПОЛУЧАТЕЛЯ</label>
      <div className="address-row">
        <input id="address" value={values.address} disabled={disabled}
          aria-describedby={fieldErrors.address ? 'address-error' : undefined}
          onChange={(event) => onChange('address', event.target.value)} />
        <button type="button" disabled={disabled} onClick={() => void onCopy()}>КОПИРОВАТЬ</button>
      </div>
      {fieldErrors.address && <p id="address-error" role="alert">{fieldErrors.address.join(' ')}</p>}

      <label htmlFor="amount">СУММА</label>
      <input id="amount" inputMode="decimal" value={values.amount} disabled={disabled}
        aria-describedby={fieldErrors.amount ? 'amount-error' : undefined}
        onChange={(event) => onChange('amount', event.target.value)} />
      {fieldErrors.amount && <p id="amount-error" role="alert">{fieldErrors.amount.join(' ')}</p>}

      <label htmlFor="network">СЕТЬ</label>
      <select id="network" value={values.network} disabled={disabled}
        onChange={(event) => onChange('network', event.target.value as Network)}>
        <option value="BTC">BTC</option><option value="ETH">ETH</option><option value="TRX">TRX</option>
      </select>

      <div className="form-actions">
        <button type="button" disabled={disabled} onClick={() => void onRefresh()}>ОБНОВИТЬ РЕКВИЗИТЫ</button>
        <button type="submit" disabled={disabled}>{checking ? 'ПРОВЕРЯЕМ…' : 'ОТПРАВИТЬ'}</button>
      </div>
    </form>
  )
}

export function ServiceMessage({ message, onRetry }: { message: string; onRetry: () => Promise<void> }) {
  return <section role="alert"><p>{message}</p><button type="button" onClick={() => void onRetry()}>ПОВТОРИТЬ</button></section>
}
```

- [ ] **Step 5: Implement App composition**

Compose `App` with no scenario-selection controls and no transfer call:

```tsx
export default function App({ dependencies }: { dependencies?: SimulationDependencies }) {
  const simulation = usePaymentSimulation(dependencies)
  const disabled = ['loadingReference', 'refreshing', 'checking'].includes(simulation.status)
  return (
    <main>
      <header className="title-bar"><h1>АНТИФРОД 2000</h1><span aria-hidden="true">×</span></header>
      <p className="simulation-notice">СИМУЛЯТОР — НАСТОЯЩАЯ ТРАНЗАКЦИЯ НЕ ВЫПОЛНЯЕТСЯ</p>
      {simulation.error && <ServiceMessage message={simulation.error} onRetry={simulation.retry} />}
      <div className="workspace">
        <section aria-label="Платёжные реквизиты">
          <PaymentForm
            values={simulation.formValues}
            disabled={disabled}
            checking={simulation.status === 'checking'}
            fieldErrors={simulation.fieldErrors}
            onChange={simulation.setField}
            onCopy={() => navigator.clipboard.writeText(simulation.copyButtonValue)}
            onRefresh={simulation.refresh}
            onSubmit={simulation.submit}
          />
        </section>
        <section aria-label="QR и статус проверки">
          <QrPreview values={simulation.qrValues} />
          <CleanStatusPanel clean={simulation.status === 'cleanResult'} />
        </section>
      </div>
      {simulation.status === 'result' && simulation.result && (
        <ResultDialog result={simulation.result} onClose={simulation.dismissResult} />
      )}
    </main>
  )
}
```

- [ ] **Step 6: Add edit/reset and keyboard tests**

Append these assertions:

```tsx
it('updates the QR summary and clears a previous clean state after editing', async () => {
  render(<App dependencies={cleanDependencies} />)
  const amount = await screen.findByLabelText('СУММА')
  await userEvent.clear(amount)
  await userEvent.type(amount, '2.5')
  expect(screen.getByRole('region', { name: 'QR и статус проверки' })).toHaveTextContent('2.5')
  expect(screen.getByText('Проверка ещё не выполнена')).toBeVisible()
})

it('closes a warning with Escape and restores submit focus', async () => {
  render(<App dependencies={tamperingDependencies} />)
  const submit = await screen.findByRole('button', { name: 'ОТПРАВИТЬ' })
  await userEvent.click(submit)
  await vi.advanceTimersByTimeAsync(5000)
  expect(screen.getByRole('dialog')).toHaveFocus()
  await userEvent.keyboard('{Escape}')
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  expect(submit).toHaveFocus()
})
```

- [ ] **Step 7: Verify all component tests green**

```powershell
npm.cmd test -- --run src/App.test.tsx
npm.cmd test -- --run
```

Expected: App tests and the complete frontend suite pass.

- [ ] **Step 8: Commit**

```powershell
git add frontend/src/components frontend/src/App.tsx frontend/src/App.test.tsx
git commit -m "feat: build accessible payment simulator ui"
```

---

### Task 6: Runet 2000 visual system and responsive layout

**Files:**
- Modify: `frontend/src/styles.css`
- Modify: `frontend/src/main.tsx`
- Modify: `frontend/index.html`
- Modify: `frontend/src/App.test.tsx`

**Interfaces:**
- Consumes: semantic component tree from Task 5.
- Produces: responsive two-column desktop layout, one-column mobile layout, retro chrome, status colors, focus visibility, reduced motion.

- [ ] **Step 1: Add a failing semantic structure test before styling**

Assert the required styling hooks exist on real semantic regions:

```tsx
expect(screen.getByRole('main')).toHaveClass('app-shell')
expect(screen.getByRole('region', { name: 'Платёжные реквизиты' })).toHaveClass('payment-panel')
expect(screen.getByRole('region', { name: 'QR и статус проверки' })).toHaveClass('status-panel')
```

- [ ] **Step 2: Verify red and add semantic classes**

Run `npm.cmd test -- --run src/App.test.tsx`, confirm the class assertions fail,
then add `className="app-shell"` to `main`, `className="payment-panel"` to the
payment region, and `className="status-panel"` to the QR/status region. Rerun
the same test until green.

- [ ] **Step 3: Implement CSS tokens and desktop layout**

Start `styles.css` with the concrete tokens and layout:

```css
:root {
  font-family: "Courier New", Consolas, monospace;
  color: #11100c;
  background: #020817;
  --navy: #061a5b;
  --beige: #d8d2bf;
  --ink: #11100c;
  --green: #169b32;
  --amber: #e6b817;
  --red: #c51f1f;
  --line: #e8dfc8;
  --hard-shadow: 5px 5px 0 #000;
}
* { box-sizing: border-box; }
body { margin: 0; min-width: 320px; min-height: 100vh; background: #020817; }
button, input, select { font: inherit; }
button { border: 3px outset var(--line); background: var(--navy); color: #fff; padding: .75rem 1rem; }
.app-shell { width: min(1120px, calc(100% - 2rem)); margin: 2rem auto; border: 4px double var(--line); background: var(--beige); box-shadow: var(--hard-shadow); }
.title-bar { display: flex; justify-content: space-between; align-items: center; padding: .75rem 1rem; color: #fff; background: var(--navy); border-bottom: 3px solid #000; }
.title-bar h1 { margin: 0; letter-spacing: .08em; }
.simulation-notice { margin: 0; padding: .75rem 1rem; color: #fff; background: #000; text-align: center; }
.workspace { display: grid; grid-template-columns: minmax(0, 1fr) minmax(18rem, .9fr); gap: 1rem; padding: 1rem; }
.payment-panel, .status-panel { min-width: 0; padding: 1rem; border: 4px double #111; background: #ded8c6; }
.address-row, .form-actions { display: flex; gap: .75rem; }
label { display: block; margin: 1rem 0 .35rem; font-weight: 700; }
input, select { width: 100%; min-height: 3rem; border: 3px inset #fff; background: #f8f3e5; padding: .65rem; }
.qr-preview { text-align: center; }
.qr-preview svg { max-width: 100%; height: auto; background: #fff; border: 8px solid #fff; }
.address-summary { overflow-wrap: anywhere; }
.clean-status { margin-top: 1rem; padding: 1rem; border: 3px solid #333; background: #aaa; font-weight: 700; text-align: center; }
.clean-status--verified { color: #fff; background: var(--green); }
.dialog-backdrop { position: fixed; inset: 0; z-index: 10; display: grid; place-items: center; padding: 1rem; background: rgb(0 0 0 / 72%); }
[role="dialog"] { width: min(650px, 100%); max-height: 90vh; overflow: auto; padding: 1.25rem; border: 6px double var(--line); background: #080808; color: #fff; box-shadow: 8px 8px 0 var(--red); }
```

- [ ] **Step 4: Implement responsive and accessible states**

Append the exact accessible/responsive rules:

```css
:focus-visible { outline: 4px solid #ffdf37; outline-offset: 3px; }
button:disabled, input:disabled, select:disabled { cursor: wait; opacity: .58; }
@media (max-width: 760px) {
  .app-shell { width: min(100% - 1rem, 1120px); margin: .5rem auto; }
  .workspace { grid-template-columns: 1fr; padding: .5rem; }
  .address-row, .form-actions { flex-direction: column; }
  .title-bar h1 { font-size: 1.35rem; }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; animation: none !important; }
}
```

- [ ] **Step 5: Run automated verification and inspect in browser**

```powershell
npm.cmd test -- --run
npm.cmd run build
npm.cmd run dev -- --open
```

Inspect desktop and a narrow browser width. Confirm no horizontal scroll,
green panel location under QR, modal layering, and readable contrast.

- [ ] **Step 6: Commit**

```powershell
git add frontend/src/styles.css frontend/src/main.tsx frontend/index.html frontend/src/App.test.tsx
git commit -m "style: add runet 2000 responsive design"
```

---

### Task 7: Live backend integration, documentation, and QA evidence

**Files:**
- Modify: `README.md`
- Create: `frontend/README.md`
- Create: `frontend/QA.md`
- Modify: frontend files only if a failing integration observation is first captured by a regression test

**Interfaces:**
- Consumes: running backend on `http://localhost:8000`, complete frontend.
- Produces: reproducible setup instructions, recorded QA matrix, verified branch ready for independent review.

- [ ] **Step 1: Verify backend health and regression suite**

From `backend/`:

```powershell
docker compose up -d
docker compose run --rm backend php artisan test
```

Expected: `/up` responds 200 and backend reports `58 passed (192 assertions)` or a newer all-green count.

- [ ] **Step 2: Run frontend verification**

From `frontend/`:

```powershell
npm.cmd test -- --run
npm.cmd run build
```

Expected: exit code 0 for both, no failed tests or TypeScript errors.

- [ ] **Step 3: Run the live user flow**

Start `npm.cmd run dev -- --open`. Confirm reference generation, form editing,
QR changes, five-second checking state, repeated same result, refreshed
requisites, clean side panel, warning dialogs, and backend error behavior.

- [ ] **Step 4: Document frontend commands**

Append this runnable block to the root README and expand the same text in
`frontend/README.md`:

````markdown
## Frontend

Frontend — тестовый симулятор, он не выполняет криптовалютные транзакции.
Перед запуском поднимите backend на `http://localhost:8000`.

```powershell
cd frontend
npm.cmd install
npm.cmd run dev
```

Проверки frontend:

```powershell
npm.cmd test -- --run
npm.cmd run build
```
````

- [ ] **Step 5: Record QA matrix**

Create `frontend/QA.md` with this non-empty matrix; fill `Факт` and `Итог` only
after each run:

```markdown
# Frontend QA

| Проверка | Подготовка | Ожидание | Факт | Итог |
|---|---|---|---|---|
| Desktop | окно ≥ 1024 px | две колонки, без горизонтального скролла | — | — |
| Mobile | окно 375 px | одна колонка, элементы доступны | — | — |
| Клавиатура | Tab, Enter, Escape | видимый фокус, modal закрывается Escape | — | — |
| Clipboard error | запрет clipboard | локальная ошибка, форма работает | — | — |
| Clean | run_id с clean | зелёная панель, modal отсутствует | — | — |
| Suspicion 7.5 | run_id с suspicion | жёлтый modal и сценарий 7.5 | — | — |
| Tampering 7.1 | назначенный run_id | красный modal, разные адреса page/QR | — | — |
| Tampering 7.2 | назначенный run_id | красный modal, copy отличается | — | — |
| Tampering 7.3 | назначенный run_id | адрес меняется в пятисекундном окне | — | — |
| Tampering 7.4 | назначенный run_id | QR-сумма отличается | — | — |
| Incomplete | fixture ответа result=null | только технический modal | — | — |
| Backend offline | остановить backend | ошибка сервиса, статус не выдуман | — | — |
| Repeat | отправить дважды без правок | тот же run_id и результат | — | — |
| Refresh | обновить реквизиты | новый run_id, зелёный статус сброшен | — | — |
```

- [ ] **Step 6: Review the exact diff**

```powershell
git status --short
git diff --check
git diff --stat develop...HEAD
```

Expected: only design/plan/frontend/README/QA changes belonging to this role;
no backend production changes.

- [ ] **Step 7: Commit documentation and QA evidence**

```powershell
git add README.md frontend/README.md frontend/QA.md
git commit -m "docs: add frontend setup and qa checklist"
```

- [ ] **Step 8: Final verification before push**

Rerun backend tests, frontend tests, and frontend build after the final commit.
Record exact counts and exit codes in the session log. Do not push or open a
pull request until the independent three-part review is complete.
