import { useMemo, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import './App.css'

const initialAddress = 'TQ7mN9xK2pL4vR8sA6dF1hJ3cB5eG7yU'

function App() {
  const [address, setAddress] = useState(initialAddress)
  const [amount, setAmount] = useState('50000')
  const [network, setNetwork] = useState('TRX')

  const qrValue = useMemo(
    () => JSON.stringify({ address, amount, network }),
    [address, amount, network],
  )

  return (
    <main className="desktop-shell">
      <header className="title-bar">
        <h1>АНТИФРОД 2000</h1>
        <span className="window-controls" aria-hidden="true">_ □ ×</span>
      </header>

      <div className="workspace">
        <section className="window payment-window" aria-labelledby="payment-title">
          <h2 id="payment-title" className="window-title">ПЕРЕВОД СРЕДСТВ</h2>
          <div className="form-body">
            <label className="field">
              <span>Адрес получателя</span>
              <input value={address} onChange={(event) => setAddress(event.target.value)} autoComplete="off" />
            </label>
            <div className="field-row">
              <label className="field">
                <span>Сумма</span>
                <input value={amount} onChange={(event) => setAmount(event.target.value)} inputMode="decimal" />
              </label>
              <label className="field network-field">
                <span>Сеть</span>
                <select value={network} onChange={(event) => setNetwork(event.target.value)}>
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
              <div className="address-preview">
                <span>АКТИВНЫЙ КОШЕЛЁК</span>
                <strong>{address || '—'}</strong>
              </div>
              <button className="refresh-button" type="button" title="Получить новые тестовые реквизиты">↻ ОБНОВИТЬ</button>
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
      </div>
      <footer>SCAMTEST SECURITY TERMINAL · BUILD 2000.08</footer>
    </main>
  )
}

export default App
