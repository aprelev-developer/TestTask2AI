import type { CheckResult } from '../api/types'

interface ResultDialogProps {
  result: CheckResult
  onClose(): void
}

export function ResultDialog({ result, onClose }: ResultDialogProps) {
  return (
    <div className="modal-backdrop" role="presentation">
      <section className="result-dialog" role="dialog" aria-modal="true" aria-labelledby="result-title">
        <h2 id="result-title">{result.result ?? result.incomplete_message ?? 'Результат проверки'}</h2>
        {result.incomplete_message && result.result && (
          <p className="incomplete-message">{result.incomplete_message}</p>
        )}
        {result.details.map((detail) => (
          <div className="result-detail" key={detail.scenario}>
            <strong>Обнаружено несоответствие</strong>
            <span>Ожидалось: {detail.expected ?? '—'}</span>
            <span>Обнаружено: {detail.actual ?? '—'}</span>
          </div>
        ))}
        <button className="send-button" type="button" onClick={onClose}>ЗАКРЫТЬ</button>
      </section>
    </div>
  )
}
