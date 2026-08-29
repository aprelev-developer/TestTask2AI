import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createReferencePayment } from './api/client'
import App from './App'

vi.mock('./api/client', () => ({
  createReferencePayment: vi.fn(),
  runCheck: vi.fn(),
}))

const reference = {
  run_id: '11111111-1111-1111-1111-111111111111',
  address: 'test-address-from-api',
  amount: '1.25',
  network: 'BTC' as const,
  allowed_scripts: ['/src/main.tsx'],
}

beforeEach(() => {
  vi.mocked(createReferencePayment).mockReset()
  vi.mocked(createReferencePayment).mockResolvedValue(reference)
})

describe('App', () => {
  it('loads payment details from the backend', async () => {
    render(<App />)

    expect(screen.getByText('ЗАГРУЗКА РЕКВИЗИТОВ...')).toBeInTheDocument()
    expect(await screen.findByText(reference.address)).toBeInTheDocument()
    expect(screen.queryByLabelText('Адрес получателя')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Сумма')).toHaveValue(reference.amount)
    expect(screen.getByLabelText('Сеть')).toHaveValue(reference.network)
    expect(screen.getByRole('button', { name: 'СКОПИРОВАТЬ' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'ОТПРАВИТЬ' })).toBeInTheDocument()
  })

  it('requests new details when refresh is clicked', async () => {
    const user = userEvent.setup()
    render(<App />)
    await screen.findByText(reference.address)

    await user.click(screen.getByRole('button', { name: /ОБНОВИТЬ/ }))

    expect(createReferencePayment).toHaveBeenCalledTimes(2)
  })

  it('shows a retry action when the backend is unavailable', async () => {
    vi.mocked(createReferencePayment).mockRejectedValueOnce(new Error('Network error'))
    const user = userEvent.setup()
    render(<App />)

    expect(await screen.findByText('Не удалось получить тестовые реквизиты')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'ПОВТОРИТЬ' }))

    expect(await screen.findByText(reference.address)).toBeInTheDocument()
  })
})
