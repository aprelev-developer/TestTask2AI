import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ResultDialog } from './ResultDialog'

describe('ResultDialog', () => {
  it('explains the finding without exposing internal scenario identifiers', () => {
    render(
      <ResultDialog
        result={{
          result: 'Есть подозрение',
          triggered_scenarios: ['7.5'],
          details: [{ scenario: '7.5', expected: 'разрешённые скрипты', actual: 'неизвестный скрипт' }],
          incomplete_checks: [],
          incomplete_message: null,
        }}
        onClose={vi.fn()}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Есть подозрение' })).toBeInTheDocument()
    expect(screen.getByText('Ожидалось: разрешённые скрипты')).toBeInTheDocument()
    expect(screen.getByText('Обнаружено: неизвестный скрипт')).toBeInTheDocument()
    expect(screen.queryByText(/7\.5/)).not.toBeInTheDocument()
    expect(screen.queryByText(/сценари/i)).not.toBeInTheDocument()
  })
})
