import { useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import type { Network } from './api/types'
import { createQrPayload } from './domain/observations'
import { usePaymentSimulation } from './hooks/usePaymentSimulation'
import './App.css'

function App() {
  const simulation = usePaymentSimulation()
  const [copyState, setCopyState] = useState<'idle' | 'copied' | 'failed'>('idle')
  const {
    status, formValues, qrValues, copyButtonValue, reference, result, error,
    fieldErrors, setField, refresh, submit, dismissResult, retry,
  } = simulation

  const isBusy = status === 'checking' || status === 'refreshing'
  const isLoading = status === 'loadingReference'
  const loadError = status === 'referenceError'

  const copyAddress = async () => {
    try {
      await navigator.clipboard.writeText(copyButtonValue)
      setCopyState('copied')
    } catch {
      setCopyState('failed')
    }
  }

  const updateAmount = (value: string) => {
    setCopyState('idle')
    setField('amount', value)
  }

  const updateNetwork = (value: Network) => {
    setCopyState('idle')
    setField('network', value)
  }

  return (
    <main className="desktop-shell">
      <header className="title-bar">
        <h1>АНТИФРОД 2000</h1>
        <span className="window-controls" aria-hidden="true">_ □ ×</span>
      </header>

      <div className="workspace">
        {!reference && (
          <section className="window connection-window" aria-live="polite">
            <h2 className="window-title">СОЕДИНЕНИЕ С BACKEND</h2>
            <div className="connection-content">
              {isLoading && <strong>ЗАГРУЗКА РЕКВИЗИТОВ...</strong>}
              {loadError && (
                <>
                  <strong>Не удалось получить тестовые реквизиты</strong>
                  <p>{error ?? 'Проверьте, что backend запущен на порту 8000.'}</p>
                  <button className="refresh-button" type="button" onClick={retry}>ПОВТОРИТЬ</button>
                </>
              )}
            </div>
          </section>
        )}

        {reference && (
          <>
            <section className="window payment-window" aria-labelledby="payment-title">
              <h2 id="payment-title" className="window-title">ПЕРЕВОД СРЕДСТВ</h2>
              <div className="form-body">
                <div className="field recipient-field">
                  <span>Адрес получателя</span>
                  <div className="recipient-card">
                    <strong>{formValues.address}</strong>
                    <button className="copy-button" type="button" onClick={copyAddress} disabled={isBusy}>СКОПИРОВАТЬ</button>
                  </div>
                  <small className={`copy-message ${copyState}`} aria-live="polite">
                    {copyState === 'copied' && 'Адрес скопирован'}
                    {copyState === 'failed' && 'Не удалось скопировать адрес'}
                  </small>
                </div>

                <div className="field-row">
                  <label className="field">
                    <span>Сумма</span>
                    <input
                      value={formValues.amount}
                      onChange={(event) => updateAmount(event.target.value)}
                      inputMode="decimal"
                      disabled={isBusy}
                      aria-invalid={Boolean(fieldErrors.amount)}
                    />
                    {fieldErrors.amount && <small className="field-error">{fieldErrors.amount[0]}</small>}
                  </label>
                  <label className="field network-field">
                    <span>Сеть</span>
                    <select value={formValues.network} onChange={(event) => updateNetwork(event.target.value as Network)} disabled={isBusy}>
                      <option value="BTC">BTC</option>
                      <option value="ETH">ETH</option>
                      <option value="TRX">TRX</option>
                    </select>
                  </label>
                </div>

                <p className="simulator-note">Учебный симулятор · реальные средства не отправляются</p>
                <button className="send-button" type="button" onClick={submit} disabled={isBusy}>
                  {status === 'checking' ? 'ПРОВЕРКА... 5 СЕК.' : 'ОТПРАВИТЬ'}
                </button>
              </div>
            </section>

            <aside className="right-column">
              <section className="window qr-window" aria-labelledby="qr-title">
                <h2 id="qr-title" className="window-title">РЕКВИЗИТЫ</h2>
                <div className="qr-content">
                  <div className="qr-frame"><QRCodeSVG value={createQrPayload(qrValues)} size={154} level="M" /></div>
                  <p className="qr-caption">СКАНИРОВАТЬ В КОШЕЛЬКЕ</p>
                  <button className="refresh-button" type="button" title="Получить новые тестовые реквизиты" onClick={refresh} disabled={isBusy}>
                    {status === 'refreshing' ? 'ЗАГРУЗКА...' : '↻ ОБНОВИТЬ'}
                  </button>
                </div>
              </section>

              <section className={`window status-window status-${status}`} aria-labelledby="status-title">
                <h2 id="status-title" className="window-title">СТАТУС ПРОВЕРКИ</h2>
                <div className="idle-status" aria-live="polite">
                  <span className="status-lamp" />
                  <div>
                    {status === 'checking' && <><strong>ИДЁТ НАБЛЮДЕНИЕ</strong><p>Фиксируем изменения в течение 5 секунд</p></>}
                    {status === 'cleanResult' && <><strong>ПОДМЕНА НЕ ОБНАРУЖЕНА</strong><p>Проверка завершена успешно</p></>}
                    {status === 'checkError' && <><strong>ОШИБКА ПРОВЕРКИ</strong><p>{error}</p><button className="inline-retry" type="button" onClick={retry}>ПОВТОРИТЬ</button></>}
                    {!['checking', 'cleanResult', 'checkError'].includes(status) && <><strong>СИСТЕМА ГОТОВА</strong><p>Нажмите «Отправить», чтобы начать наблюдение</p></>}
                  </div>
                </div>
              </section>
            </aside>
          </>
        )}
      </div>

      {status === 'result' && result && (
        <div className="modal-backdrop" role="presentation">
          <section className="result-dialog" role="dialog" aria-modal="true" aria-labelledby="result-title">
            <h2 id="result-title">{result.result ?? result.incomplete_message}</h2>
            {result.incomplete_message && result.result && <p className="incomplete-message">{result.incomplete_message}</p>}
            {result.triggered_scenarios.length > 0 && <p>Сценарии: {result.triggered_scenarios.join(', ')}</p>}
            {result.details.map((detail) => (
              <div className="result-detail" key={detail.scenario}>
                <strong>Сценарий {detail.scenario}</strong>
                <span>Ожидалось: {detail.expected ?? '—'}</span>
                <span>Обнаружено: {detail.actual ?? '—'}</span>
              </div>
            ))}
            <button className="send-button" type="button" onClick={dismissResult}>ЗАКРЫТЬ</button>
          </section>
        </div>
      )}

      <footer>SCAMTEST SECURITY TERMINAL · BUILD 2000.08</footer>
    </main>
  )
}

export default App
