import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import type { CheckResult } from '../api/types'
import { ResultDialog } from './ResultDialog'

const baseResult: CheckResult = {
  result: 'Есть подозрение',
  triggered_scenarios: ['7.5'],
  details: [{ scenario: '7.5', expected: 'SECRET BACKEND EXPECTED', actual: 'SECRET BACKEND ACTUAL' }],
  incomplete_checks: [],
  incomplete_message: null,
}

describe('ResultDialog', () => {
  it('shows a user-facing suspicion status without raw backend details', () => {
    render(<ResultDialog result={baseResult} onClose={vi.fn()} />)

    expect(screen.getByRole('heading', { name: 'ТРАНЗАКЦИЯ ВЫЗЫВАЕТ ПОДОЗРЕНИЕ' })).toBeInTheDocument()
    expect(screen.getByText(/перепроверьте адрес/i)).toBeInTheDocument()
    expect(screen.queryByText(/SECRET BACKEND/)).not.toBeInTheDocument()
    expect(screen.queryByText(/7\.5/)).not.toBeInTheDocument()
    expect(screen.queryByText(/ожидалось|обнаружено:/i)).not.toBeInTheDocument()
  })

  it('uses a stronger warning for detected tampering', () => {
    render(<ResultDialog result={{ ...baseResult, result: 'Обнаружена подмена' }} onClose={vi.fn()} />)
    expect(screen.getByRole('heading', { name: 'ОПАСНО: ВОЗМОЖНА ПОДМЕНА' })).toBeInTheDocument()
    expect(screen.getByText(/не отправляйте средства/i)).toBeInTheDocument()
  })

  it('does not claim safety when the check is incomplete', () => {
    render(<ResultDialog result={{ ...baseResult, result: null, incomplete_message: 'RAW BACKEND MESSAGE' }} onClose={vi.fn()} />)
    expect(screen.getByRole('heading', { name: 'СТАТУС НЕ ОПРЕДЕЛЁН' })).toBeInTheDocument()
    expect(screen.getByText(/проверку не удалось завершить/i)).toBeInTheDocument()
    expect(screen.queryByText('RAW BACKEND MESSAGE')).not.toBeInTheDocument()
  })
})
