import { useCallback, useEffect, useRef, useState } from 'react'
import { createReferencePayment, runCheck } from '../api/client'
import type { CheckResult, CreateReferencePaymentInput, ReferencePayment } from '../api/types'
import {
  buildCheckRequest,
  createScenarioObservations,
  paymentFingerprint,
  substitutedAddress,
} from '../domain/observations'
import type { PaymentValues } from '../domain/observations'
import { deterministicScenarioSource } from '../domain/scenario'
import type { ScenarioSource } from '../domain/scenario'

export type SimulationStatus =
  | 'loadingReference'
  | 'ready'
  | 'refreshing'
  | 'checking'
  | 'cleanResult'
  | 'result'
  | 'referenceError'
  | 'checkError'

interface SimulationApi {
  createReferencePayment(input?: CreateReferencePaymentInput): Promise<ReferencePayment>
  runCheck(input: Parameters<typeof runCheck>[0]): Promise<CheckResult>
}

export interface SimulationDependencies {
  api: SimulationApi
  scenarioSource: ScenarioSource
  wait(milliseconds: number): Promise<void>
  getPageScripts(): string[]
  attachDemoScript(): () => void
}

const defaultDependencies: SimulationDependencies = {
  api: { createReferencePayment, runCheck },
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

const emptyValues: PaymentValues = { address: '', amount: '', network: 'BTC' }

export function usePaymentSimulation(
  dependencies: SimulationDependencies = defaultDependencies,
) {
  const { api, scenarioSource, wait, getPageScripts, attachDemoScript } = dependencies
  const [status, setStatus] = useState<SimulationStatus>('loadingReference')
  const [formValues, setFormValues] = useState<PaymentValues>(emptyValues)
  const [qrValues, setQrValues] = useState<PaymentValues>(emptyValues)
  const [copyButtonValue, setCopyButtonValue] = useState('')
  const [reference, setReference] = useState<ReferencePayment | null>(null)
  const [referenceFingerprint, setReferenceFingerprint] = useState<string | null>(null)
  const [result, setResult] = useState<CheckResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const busy = useRef(false)
  const mounted = useRef(true)
  const activeCleanup = useRef<() => void>(() => undefined)

  const applyReference = useCallback((created: ReferencePayment) => {
    const values: PaymentValues = {
      address: created.address,
      amount: created.amount,
      network: created.network,
    }
    const scenario = scenarioSource.forRun(created.run_id)
    const observations = createScenarioObservations(created, scenario)
    setReference(created)
    setFormValues(values)
    setQrValues(observations.qrValues)
    setCopyButtonValue(observations.copyButtonValue)
    setReferenceFingerprint(paymentFingerprint(values))
    setResult(null)
    setError(null)
    setFieldErrors({})
    setStatus('ready')
  }, [scenarioSource])

  const refresh = useCallback(async () => {
    if (busy.current) return
    busy.current = true
    setStatus(reference ? 'refreshing' : 'loadingReference')
    setError(null)
    try {
      const created = await api.createReferencePayment({ allowed_scripts: getPageScripts() })
      if (mounted.current) applyReference(created)
    } catch {
      if (!mounted.current) return
      setError('Не удалось получить тестовые реквизиты. Проверьте подключение и повторите попытку.')
      setStatus('referenceError')
    } finally {
      busy.current = false
    }
  }, [api, applyReference, getPageScripts, reference])

  useEffect(() => {
    mounted.current = true
    busy.current = true
    void api.createReferencePayment({ allowed_scripts: getPageScripts() })
      .then((created) => {
        if (mounted.current) applyReference(created)
      })
      .catch(() => {
        if (!mounted.current) return
        setError('Не удалось получить тестовые реквизиты. Проверьте подключение и повторите попытку.')
        setStatus('referenceError')
      })
      .finally(() => { busy.current = false })

    return () => {
      mounted.current = false
      activeCleanup.current()
    }
  }, [api, applyReference, getPageScripts])

  function setField<K extends keyof PaymentValues>(field: K, value: PaymentValues[K]) {
    setFormValues((current) => {
      const updated = { ...current, [field]: value }
      setQrValues(updated)
      setCopyButtonValue(updated.address)
      return updated
    })
    setResult(null)
    setError(null)
    setFieldErrors({})
    setStatus('ready')
  }

  async function submit() {
    if (busy.current || !reference) return
    const validation: Record<string, string[]> = {}
    if (!/^\d+(\.\d+)?$/.test(formValues.amount)) validation.amount = ['Укажите сумму числом']
    if (Object.keys(validation).length > 0) {
      setFieldErrors(validation)
      return
    }

    busy.current = true
    setStatus('checking')
    setError(null)
    setFieldErrors({})
    setResult(null)
    const originalValues = formValues
    let cleanupDemoScript: () => void = () => undefined

    try {
      let activeReference = reference
      if (paymentFingerprint(formValues) !== referenceFingerprint) {
        activeReference = await api.createReferencePayment({
          address: formValues.address,
          amount: formValues.amount,
          network: formValues.network,
          allowed_scripts: getPageScripts(),
        })
        if (!mounted.current) return
        setReference(activeReference)
        setReferenceFingerprint(paymentFingerprint(formValues))
      }

      const scenario = scenarioSource.forRun(activeReference.run_id)
      const observations = createScenarioObservations(activeReference, scenario)
      setQrValues(observations.qrValues)
      setCopyButtonValue(observations.copyButtonValue)

      if (scenario.kind === 'suspicion') {
        cleanupDemoScript = attachDemoScript()
        activeCleanup.current = cleanupDemoScript
      }
      if (scenario.kind === 'tampering' && scenario.scenario === '7.3') {
        setFormValues({ ...formValues, address: substitutedAddress(formValues.address) })
      }

      await wait(5000)
      if (!mounted.current) return
      const response = await api.runCheck(
        buildCheckRequest(activeReference, scenario, getPageScripts()),
      )
      if (!mounted.current) return
      setResult(response)
      setStatus(response.result === 'Подмена не обнаружена' ? 'cleanResult' : 'result')
    } catch {
      if (!mounted.current) return
      setError('Проверку не удалось выполнить. Проверьте подключение и повторите попытку.')
      setResult(null)
      setStatus('checkError')
    } finally {
      cleanupDemoScript()
      activeCleanup.current = () => undefined
      if (mounted.current) setFormValues(originalValues)
      busy.current = false
    }
  }

  function dismissResult() {
    setResult(null)
    setStatus('ready')
  }

  return {
    status,
    formValues,
    qrValues,
    copyButtonValue,
    reference,
    result,
    error,
    fieldErrors,
    setField,
    refresh,
    submit,
    dismissResult,
    retry: status === 'referenceError' ? refresh : submit,
  }
}
