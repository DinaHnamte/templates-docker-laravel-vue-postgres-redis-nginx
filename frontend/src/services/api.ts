const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api';

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

type RequestOptions<TBody> = {
  path: string;
  method?: HttpMethod;
  body?: TBody;
  token?: string | null;
};

async function request<TResponse, TBody = unknown>({
  path,
  method = 'GET',
  body,
  token,
}: RequestOptions<TBody>): Promise<TResponse> {
  const res = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (!res.ok) {
    let message = 'Request failed';
    try {
      const data = await res.json();
      message = data.message ?? message;
    } catch {
      // ignore json parse errors
    }
    throw new Error(message);
  }

  return (await res.json()) as TResponse;
}





