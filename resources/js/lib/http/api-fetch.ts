interface ApiFetchOptions extends Omit<RequestInit, 'body'> {
  body?: Record<string, unknown>;
}

interface ApiResponse<T> {
  ok: boolean;
  status: number;
  data: T | null;
  errorText?: string;
}

function getXsrfToken(): string {
  const match = document.cookie
    .split('; ')
    .find((row) => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];

  return match ? decodeURIComponent(match) : '';
}

function normalizeHeaders(headers?: HeadersInit): Record<string, string> {
  if (!headers) {
    return {};
  }

  if (headers instanceof Headers) {
    return Object.fromEntries(headers.entries());
  }

  if (Array.isArray(headers)) {
    return Object.fromEntries(headers);
  }

  return headers;
}

/** Adds CSRF handling for non-Inertia JSON endpoints; use router.visit/patch/delete for Inertia. */
export async function apiFetch<T = unknown>(
  url: string,
  options: ApiFetchOptions = {},
): Promise<ApiResponse<T>> {
  const { body, headers, ...rest } = options;
  const resolvedHeaders = normalizeHeaders(headers);

  const response = await fetch(url, {
    ...rest,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-XSRF-TOKEN': getXsrfToken(),
      ...resolvedHeaders,
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  let data: T | null = null;
  const text = await response.text();

  if (text) {
    try {
      data = JSON.parse(text) as T;
    } catch {
      // Non-JSON response (e.g., HTML error page) - leave data as null
    }
  }

  return {
    ok: response.ok,
    status: response.status,
    data,
    errorText: !response.ok && data === null ? text : undefined,
  };
}
