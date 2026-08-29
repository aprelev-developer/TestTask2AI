import type { CheckInput, Network, ReferencePayment } from '../api/types'
import type { FraudScenario } from './scenario'

export interface PaymentValues {
  address: string
  amount: string
  network: Network
}

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

function substitutedAmount(amount: string): string {
  return amount === '999.99999999' ? '998.99999999' : '999.99999999'
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
    qrValues: {
      address: reference.address,
      amount: reference.amount,
      network: reference.network,
    },
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

export function buildCheckRequest(
  reference: ReferencePayment,
  scenario: FraudScenario,
  observedScripts: string[],
): CheckInput {
  const observations = createScenarioObservations(reference, scenario)

  return {
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
}
