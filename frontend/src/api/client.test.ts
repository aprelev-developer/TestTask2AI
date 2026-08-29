import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, createReferencePayment, runCheck } from './client'

afterEach(() => vi.restoreAllMocks())

describe('API client', () => {
  it('creates a reference payment using the backend contract', async () => {
    const responseBody = {
      run_id: '11111111-1111-1111-1111-111111111111',
      address: 'test-address',
      amount: '1.25',
      network: 'BTC' as const,
      allowed_scripts: ['/src/main.tsx'],
    }
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify(responseBody), { status: 201 }),
    )

    await expect(createReferencePayment()).resolves.toEqual(responseBody)
    expect(fetchMock).toHaveBeenCalledWith('/api/reference-payments', expect.objectContaining({
      method: 'POST',
      body: '{}',
    }))
  })

  it('sends observations to the checks endpoint', async () => {
    const result = {
      result: 'Подмена не обнаружена' as const,
      triggered_scenarios: [],
      details: [],
      incomplete_checks: [],
      incomplete_message: null,
    }
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify(result), { status: 200 }),
    )
    const input = {
      run_id: '11111111-1111-1111-1111-111111111111',
      displayed_address: 'test-address', displayed_amount: '1.25', displayed_network: 'BTC',
      qr_address: 'test-address', qr_amount: '1.25', qr_network: 'BTC',
      copy_button_value: 'test-address', address_after_watch_window: 'test-address',
      page_scripts: ['/src/main.tsx'],
    }

    await expect(runCheck(input)).resolves.toEqual(result)
    expect(fetchMock).toHaveBeenCalledWith('/api/checks', expect.objectContaining({ method: 'POST' }))
  })

  it('surfaces the backend error message', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ error: { message: 'Некорректная сумма' } }), { status: 422 }),
    )

    await expect(createReferencePayment({ amount: 'abc' })).rejects.toEqual(
      new ApiError('Некорректная сумма', 422),
    )
  })
})
