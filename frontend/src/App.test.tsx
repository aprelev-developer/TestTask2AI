import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import App from './App'

describe('App', () => {
  it('shows the payment simulator controls', () => {
    render(<App />)

    expect(screen.getByRole('heading', { name: 'АНТИФРОД 2000' })).toBeInTheDocument()
    expect(screen.getByLabelText('Адрес получателя')).toBeInTheDocument()
    expect(screen.getByLabelText('Сумма')).toBeInTheDocument()
    expect(screen.getByLabelText('Сеть')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'ОТПРАВИТЬ' })).toBeInTheDocument()
  })
})
