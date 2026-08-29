import { useEffect, useRef } from 'react'
import type { CheckResult } from '../api/types'

interface ResultDialogProps {
  result: CheckResult
  onClose(): void
}

function presentationFor(result: CheckResult) {
  if (result.result === 'Обнаружена подмена') {
    return {
      level: 'danger',
      icon: '×',
      title: 'ОПАСНО: ВОЗМОЖНА ПОДМЕНА',
      message: 'Платёжные данные могли быть изменены. Не отправляйте средства и получите реквизиты заново из доверенного источника.',
    }
  }

  if (result.result === 'Есть подозрение') {
    return {
      level: 'warning',
      icon: '!',
      title: 'ТРАНЗАКЦИЯ ВЫЗЫВАЕТ ПОДОЗРЕНИЕ',
      message: 'Перед продолжением перепроверьте адрес получателя и сеть через другой доверенный канал.',
    }
  }

  return {
    level: 'unknown',
    icon: '?',
    title: 'СТАТУС НЕ ОПРЕДЕЛЁН',
    message: 'Проверку не удалось завершить полностью. Не считайте транзакцию безопасной и повторите проверку.',
  }
}

export function ResultDialog({ result, onClose }: ResultDialogProps) {
  const presentation = presentationFor(result)
  const closeButton = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
    closeButton.current?.focus()
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', closeOnEscape)
    return () => {
      document.removeEventListener('keydown', closeOnEscape)
      previousFocus?.focus()
    }
  }, [onClose])

  return (
    <div className="modal-backdrop" role="presentation">
      <section className={`result-dialog result-${presentation.level}`} role="dialog" aria-modal="true" aria-labelledby="result-title">
        <span className="result-icon" aria-hidden="true">{presentation.icon}</span>
        <h2 id="result-title">{presentation.title}</h2>
        <p className="result-guidance">{presentation.message}</p>
        <button ref={closeButton} className="send-button" type="button" onClick={onClose}>ВЕРНУТЬСЯ К РЕКВИЗИТАМ</button>
      </section>
    </div>
  )
}
