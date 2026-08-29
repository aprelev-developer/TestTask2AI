import { useCallback, useEffect, useMemo, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { createReferencePayment } from './api/client'
import type { Network, ReferencePayment } from './api/types'
import './App.css'

function App() {
  const [reference, setReference] = useState<ReferencePayment | null>(null)
  const [amount, setAmount] = useState('')
  const [network, setNetwork] = useState<Network>('BTC')
  const [copyState, setCopyState] = useState<'idle' | 'copied' | 'failed'>('idle')
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState(false)

  const address = reference?.address ?? ''

  const loadReference = useCallback(async () => {
    setIsLoading(true)
    setLoadError(false)
    setCopyState('idle')
    try {
      const payment = await createReferencePayment()
      setReference(payment)
      setAmount(payment.amount)
      setNetwork(payment.network)
    } catch {
      setReference(null)
      setLoadError(true)
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    let isActive = true

    void createReferencePayment()
      .then((payment) => {
        if (!isActive) return
        setReference(payment)
        setAmount(payment.amount)
        setNetwork(payment.network)
      })
      .catch(() => {
        if (!isActive) return
        setLoadError(true)
      })
      .finally(() => {
        if (isActive) setIsLoading(false)
      })

    return () => {
      isActive = false
    }
  }, [])

  const qrValue = useMemo(
    () => JSON.stringify({ address, amount, network }),
    [address, amount, network],
  )

  const copyAddress = async () => {
    try {
      await navigator.clipboard.writeText(address)
      setCopyState('copied')
    } catch {
      setCopyState('failed')
    }
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
                  <p>Проверьте, что backend запущен на порту 8000.</p>
                  <button className="refresh-button" type="button" onClick={loadReference}>ПОВТОРИТЬ</button>
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
                <strong>{address}</strong>
                <button className="copy-button" type="button" onClick={copyAddress}>СКОПИРОВАТЬ</button>
              </div>
              <small className={`copy-message ${copyState}`} aria-live="polite">
                {copyState === 'copied' && 'Адрес скопирован'}
                {copyState === 'failed' && 'Не удалось скопировать адрес'}
              </small>
            </div>
            <div className="field-row">
              <label className="field">
                <span>Сумма</span>
                <input value={amount} onChange={(event) => setAmount(event.target.value)} inputMode="decimal" />
              </label>
              <label className="field network-field">
                <span>Сеть</span>
                <select value={network} onChange={(event) => setNetwork(event.target.value as Network)}>
                  <option value="BTC">BTC</option>
                  <option value="ETH">ETH</option>
                  <option value="TRX">TRX</option>
                </select>
              </label>
            </div>
            <p className="simulator-note">Учебный симулятор · реальные средства не отправляются</p>
            <button className="send-button" type="button">ОТПРАВИТЬ</button>
          </div>
        </section>

        <aside className="right-column">
          <section className="window qr-window" aria-labelledby="qr-title">
            <h2 id="qr-title" className="window-title">РЕКВИЗИТЫ</h2>
            <div className="qr-content">
              <div className="qr-frame"><QRCodeSVG value={qrValue} size={154} level="M" /></div>
              <p className="qr-caption">СКАНИРОВАТЬ В КОШЕЛЬКЕ</p>
              <button className="refresh-button" type="button" title="Получить новые тестовые реквизиты" onClick={loadReference} disabled={isLoading}>
                {isLoading ? 'ЗАГРУЗКА...' : '↻ ОБНОВИТЬ'}
              </button>
            </div>
          </section>

          <section className="window status-window" aria-labelledby="status-title">
            <h2 id="status-title" className="window-title">СТАТУС ПРОВЕРКИ</h2>
            <div className="idle-status">
              <span className="status-lamp" />
              <div><strong>СИСТЕМА ГОТОВА</strong><p>Нажмите «Отправить», чтобы начать наблюдение</p></div>
            </div>
          </section>
        </aside>
          </>
        )}
      </div>
      <footer>SCAMTEST SECURITY TERMINAL · BUILD 2000.08</footer>
    </main>
  )
}

export default App
