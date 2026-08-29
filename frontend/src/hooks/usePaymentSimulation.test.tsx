import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { usePaymentSimulation } from './usePaymentSimulation'

const reference = {
  run_id: '11111111-1111-1111-1111-111111111111',
  address: 'test-address', amount: '1.0', network: 'BTC' as const,
  allowed_scripts: ['/src/main.tsx'],
}
const cleanResult = {
  result: 'Подмена не обнаружена' as const,
  triggered_scenarios: [], details: [], incomplete_checks: [], incomplete_message: null,
}

const createApi = () => ({
  createReferencePayment: vi.fn().mockResolvedValue(reference),
  runCheck: vi.fn().mockResolvedValue(cleanResult),
})

describe('usePaymentSimulation', () => {
  let api: ReturnType<typeof createApi>

  beforeEach(() => { api = createApi() })

  function dependencies(wait = (_milliseconds: number) => Promise.resolve()) {
    return {
      api,
      scenarioSource: { forRun: () => ({ kind: 'clean' as const }) },
      wait,
      getPageScripts: () => reference.allowed_scripts,
      attachDemoScript: () => () => undefined,
    }
  }

  it('loads generated values and starts ready', async () => {
    const deps = dependencies()
    const { result } = renderHook(() => usePaymentSimulation(deps))
    await waitFor(() => expect(result.current.status).toBe('ready'))
    expect(result.current.formValues).toEqual({ address: 'test-address', amount: '1.0', network: 'BTC' })
  })

  it('creates a new reference after editing the amount', async () => {
    const deps = dependencies()
    const { result } = renderHook(() => usePaymentSimulation(deps))
    await waitFor(() => expect(result.current.status).toBe('ready'))
    act(() => result.current.setField('amount', '2.0'))
    await act(async () => result.current.submit())
    expect(api.createReferencePayment).toHaveBeenLastCalledWith(expect.objectContaining({ amount: '2.0' }))
    expect(api.runCheck).toHaveBeenCalledTimes(1)
  })

  it('waits exactly five seconds before calling the checks API', async () => {
    vi.useFakeTimers()
    const timedWait = (milliseconds: number) => new Promise<void>((resolve) => setTimeout(resolve, milliseconds))
    const deps = dependencies(timedWait)
    const { result } = renderHook(() => usePaymentSimulation(deps))
    await act(async () => Promise.resolve())
    const submission = act(async () => result.current.submit())
    await vi.advanceTimersByTimeAsync(4999)
    expect(api.runCheck).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    await submission
    expect(api.runCheck).toHaveBeenCalledTimes(1)
    vi.useRealTimers()
  })

  it('uses a side-panel state for a clean response and clears it on edit', async () => {
    const deps = dependencies()
    const { result } = renderHook(() => usePaymentSimulation(deps))
    await waitFor(() => expect(result.current.status).toBe('ready'))
    await act(async () => result.current.submit())
    expect(result.current.status).toBe('cleanResult')
    act(() => result.current.setField('amount', '2.0'))
    expect(result.current.status).toBe('ready')
    expect(result.current.result).toBeNull()
  })

  it('does not invent a result when the checks API fails', async () => {
    api.runCheck.mockRejectedValueOnce(new Error('Backend offline'))
    const deps = dependencies()
    const { result } = renderHook(() => usePaymentSimulation(deps))
    await waitFor(() => expect(result.current.status).toBe('ready'))
    await act(async () => result.current.submit())
    expect(result.current.status).toBe('checkError')
    expect(result.current.result).toBeNull()
    expect(result.current.error).toBe('Проверку не удалось выполнить. Проверьте подключение и повторите попытку.')
  })
})
