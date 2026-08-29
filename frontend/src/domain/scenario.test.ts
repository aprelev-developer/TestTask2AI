import { describe, expect, it } from 'vitest'
import { deterministicScenarioSource } from './scenario'

describe('deterministicScenarioSource', () => {
  it('returns the same scenario for the same run id', () => {
    const runId = '11111111-1111-1111-1111-111111111111'
    expect(deterministicScenarioSource.forRun(runId))
      .toEqual(deterministicScenarioSource.forRun(runId))
  })

  it('can produce every result kind in a representative sample', () => {
    const kinds = new Set(
      Array.from({ length: 300 }, (_, index) =>
        deterministicScenarioSource.forRun(`run-${index}`).kind),
    )
    expect(kinds).toEqual(new Set(['clean', 'suspicion', 'tampering']))
  })

  it('can produce all four direct tampering variants', () => {
    const variants = new Set(
      Array.from({ length: 1000 }, (_, index) => deterministicScenarioSource.forRun(`run-${index}`))
        .filter((scenario) => scenario.kind === 'tampering')
        .map((scenario) => scenario.scenario),
    )
    expect(variants).toEqual(new Set(['7.1', '7.2', '7.3', '7.4']))
  })
})
