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

/**
 * Fetch utility for JSON API endpoints with automatic CSRF handling.
 * Use for non-Inertia JSON endpoints (e.g., notifier test).
 * For Inertia mutations, use router.visit/patch/delete instead.
 */
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

  // Parse response safely - handle empty body (204), non-JSON, or errors
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
