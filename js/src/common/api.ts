import app from 'flarum/common/app';
import extractText from 'flarum/common/utils/extractText';
import type RequestError from 'flarum/common/utils/RequestError';

export type Method = 'GET' | 'POST' | 'PATCH' | 'DELETE';

export interface RequestOptions {
  /** Show no alert on an error: the caller shows the reason itself. */
  quiet?: boolean;
  /** Send the body as it is (a FormData upload) instead of as JSON. */
  raw?: boolean;
}

/** A translation, or null when the key is not in the locale. */
function known(key: string): string | null {
  return app.translator.trans(key) !== key ? extractText(app.translator.trans(key)) : null;
}

/**
 * The sentence for an error answer from our endpoints: they answer refusals
 * with a code only, which has its own sentence; a field that fails validation
 * has its detail; otherwise Flarum's own for the status, or the generic one.
 */
export function errorText(error: RequestError | unknown): string {
  const response = (error as RequestError | undefined)?.response as { errors?: { code?: string; detail?: string }[] } | null | undefined;
  const status = (error as RequestError | undefined)?.status;
  const first = response?.errors?.[0];
  if (first?.code === 'validation_error' && first.detail) return first.detail;
  const coded = first?.code ? known(`ernestdefoe-sticker-book.lib.errors.${first.code}`) : null;
  if (coded) return coded;
  if (status === 0) return extractText(app.translator.trans('core.lib.error.network_message'));
  if (status === 403 || status === 401) return extractText(app.translator.trans('core.lib.error.permission_denied_message'));
  if (status === 404) return extractText(app.translator.trans('core.lib.error.not_found_message'));
  if (first?.detail) return first.detail;
  return extractText(app.translator.trans('core.lib.error.generic_message'));
}

/** An errorHandler for app.request, the store and models: the error's sentence as an alert, then reject. */
export function alertError(error: RequestError): never {
  app.alerts.show({ type: 'error' }, errorText(error));
  throw error;
}

/** An errorHandler that only rejects: the caller shows the reason itself. */
export function quietError(error: RequestError): never {
  throw error;
}

/**
 * A request to our API (`path` under /api). A GET sends its data as the query
 * string and runs in the background; an error shows its sentence as an alert
 * (unless quiet) and rejects.
 */
export function request<T>(method: Method, path: string, data?: Record<string, unknown> | FormData, options: RequestOptions = {}): Promise<T> {
  const url = app.forum.attribute<string>('apiUrl') + path;
  const query = method === 'GET' && data ? '?' + new URLSearchParams(data as Record<string, string>).toString() : '';
  return app.request<T>({
    method,
    url: url + query,
    body: method === 'GET' ? undefined : data,
    background: method === 'GET',
    ...(options.raw ? { serialize: (body: unknown) => body } : {}),
    errorHandler: options.quiet ? quietError : alertError,
  });
}

/** A request to our /api/sticker-book endpoints (album, spares, board, packs, reactions, gifts). */
export function call<T>(method: Method, path: string, data?: Record<string, unknown> | FormData, options: RequestOptions = {}): Promise<T> {
  return request<T>(method, '/sticker-book' + path, data, options);
}
