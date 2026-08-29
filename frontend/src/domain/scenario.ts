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
