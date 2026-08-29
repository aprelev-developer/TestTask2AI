import { describe, expect, it } from 'vitest'
import type { ReferencePayment } from '../api/types'
import { buildCheckRequest, createQrPayload, createScenarioObservations, paymentFingerprint } from './observations'

const reference: ReferencePayment = {
  run_id: '11111111-1111-1111-1111-111111111111',
  address: 'test-address', amount: '1.00000000', network: 'BTC',
  allowed_scripts: ['/src/main.tsx'],
}

describe('observations', () => {
  it('uses one serialization for the QR payload', () => {
    expect(createQrPayload(reference)).toBe(
      '{"address":"test-address","amount":"1.00000000","network":"BTC"}',
    )
  })

  it('fingerprints all payment values', () => {
    expect(paymentFingerprint(reference)).not.toBe(
      paymentFingerprint({ ...reference, amount: '2.00000000' }),
    )
  })

  it('builds complete matching observations for a clean run', () => {
    expect(buildCheckRequest(reference, { kind: 'clean' }, reference.allowed_scripts))
      .toMatchObject({
        run_id: reference.run_id,
        displayed_address: 'test-address', displayed_amount: '1.00000000', displayed_network: 'BTC',
        qr_address: 'test-address', qr_amount: '1.00000000', qr_network: 'BTC',
        copy_button_value: 'test-address', address_after_watch_window: 'test-address',
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

  it('exposes the actual QR and copy values for the interface', () => {
    const observations = createScenarioObservations(reference, { kind: 'tampering', scenario: '7.2' })
    expect(observations.qrValues.address).toBe(reference.address)
    expect(observations.copyButtonValue).not.toBe(reference.address)
  })

  it('passes the actually observed extra script for scenario 7.5', () => {
    const scripts = [...reference.allowed_scripts, '/untrusted-demo.js']
    expect(buildCheckRequest(reference, { kind: 'suspicion', scenario: '7.5' }, scripts).page_scripts)
      .toEqual(scripts)
  })
})
