import type {
  ApiErrorBody,
  CheckInput,
  CheckResult,
  CreateReferencePaymentInput,
  ReferencePayment,
} from './types'

export class ApiError extends Error {
  public readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

async function request<T>(path: string, init: RequestInit): Promise<T> {
  const response = await fetch(path, {
    ...init,
    headers: { 'Content-Type': 'application/json', ...init.headers },
  })

  const body = (await response.json()) as T | ApiErrorBody

  if (!response.ok) {
    const errorBody = body as ApiErrorBody
    throw new ApiError(errorBody.error?.message ?? 'Backend вернул ошибку', response.status)
  }

  return body as T
}

export function createReferencePayment(
  input: CreateReferencePaymentInput = {},
): Promise<ReferencePayment> {
  return request('/api/reference-payments', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export function runCheck(input: CheckInput): Promise<CheckResult> {
  return request('/api/checks', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}
