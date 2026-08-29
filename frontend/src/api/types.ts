export type Network = 'BTC' | 'ETH' | 'TRX'

export interface ReferencePayment {
  run_id: string
  address: string
  amount: string
  network: Network
  allowed_scripts: string[]
}

export interface CreateReferencePaymentInput {
  address?: string
  amount?: string
  network?: Network
  allowed_scripts?: string[]
}

export interface CheckInput {
  run_id: string
  displayed_address: string
  displayed_amount: string
  displayed_network: string
  qr_address: string | null
  qr_amount: string | null
  qr_network: string | null
  copy_button_value: string | null
  address_after_watch_window: string | null
  page_scripts: string[] | null
}

export type CheckStatus =
  | 'Подмена не обнаружена'
  | 'Есть подозрение'
  | 'Обнаружена подмена'
  | null

export interface CheckDetail {
  scenario: string
  expected: string | null
  actual: string | null
}

export interface CheckResult {
  result: CheckStatus
  triggered_scenarios: string[]
  details: CheckDetail[]
  incomplete_checks: string[]
  incomplete_message: string | null
}

export interface ApiErrorBody {
  error?: {
    message?: string
    fields?: Record<string, string[]>
  }
}
