import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import App from './App'

describe('App', () => {
  it('shows the payment simulator controls', () => {
    render(<App />)

    expect(screen.getByRole('heading', { name: 'АНТИФРОД 2000' })).toBeInTheDocument()
    expect(screen.getByText('TQ7mN9xK2pL4vR8sA6dF1hJ3cB5eG7yU')).toBeInTheDocument()
    expect(screen.queryByLabelText('Адрес получателя')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'СКОПИРОВАТЬ' })).toBeInTheDocument()
    expect(screen.getByLabelText('Сумма')).toBeInTheDocument()
    expect(screen.getByLabelText('Сеть')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'ОТПРАВИТЬ' })).toBeInTheDocument()
  })
})
