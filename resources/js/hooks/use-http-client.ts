import {
  CancelToken,
  FormDataConvertible,
  hasFiles,
  http,
  HttpCancelledError,
  HttpError,
  HttpProgressEvent,
  HttpResponseError,
  HttpResponseHeaders,
  mergeDataIntoQueryString,
  Method,
  objectToFormData,
} from "@inertiajs/core";
import { useCallback, useMemo, useRef } from "react";

type RequestData = Record<string, FormDataConvertible>;

export interface HttpClientResponse<TData = unknown> {
  data: TData;
  status: number;
  headers: HttpResponseHeaders;
}

function isHttpError(error: unknown): error is HttpError {
  return error instanceof HttpError;
}

function isHttpResponseError(error: unknown): error is HttpResponseError {
  return error instanceof HttpResponseError;
}

export interface UseHttpClientOptions<TResponse = unknown> {
  headers?: Record<string, string>;
  onBefore?: () => boolean | void;
  onStart?: () => void;
  onProgress?: (progress: HttpProgressEvent) => void;
  onSuccess?: (response: TResponse) => void;
  onFinish?: () => void;
  onCancel?: () => void;
  onCancelToken?: (cancelToken: CancelToken) => void;
}

export interface UseHttpClientReturn {
  isHttpError: typeof isHttpError;
  isHttpResponseError: typeof isHttpResponseError;
  submit: <TResponse = unknown>(
    method: Method,
    url: string,
    data?: RequestData,
    options?: UseHttpClientOptions<TResponse>,
  ) => Promise<HttpClientResponse<TResponse>>;
  get: <TResponse = unknown>(
    url: string,
    data?: RequestData,
    options?: UseHttpClientOptions<TResponse>,
  ) => Promise<HttpClientResponse<TResponse>>;
  post: <TResponse = unknown>(
    url: string,
    data?: RequestData,
    options?: UseHttpClientOptions<TResponse>,
  ) => Promise<HttpClientResponse<TResponse>>;
  put: <TResponse = unknown>(
    url: string,
    data?: RequestData,
    options?: UseHttpClientOptions<TResponse>,
  ) => Promise<HttpClientResponse<TResponse>>;
  patch: <TResponse = unknown>(
    url: string,
    data?: RequestData,
    options?: UseHttpClientOptions<TResponse>,
  ) => Promise<HttpClientResponse<TResponse>>;
  delete: <TResponse = unknown>(
    url: string,
    data?: RequestData,
    options?: UseHttpClientOptions<TResponse>,
  ) => Promise<HttpClientResponse<TResponse>>;
  cancel: () => void;
}

export default function useHttpClient(): UseHttpClientReturn {
  const abortController = useRef<AbortController | null>(null);

  const submit = useCallback(
    async <TResponse = unknown>(
      method: Method,
      url: string,
      data: RequestData = {},
      options: UseHttpClientOptions<TResponse> = {},
    ): Promise<HttpClientResponse<TResponse>> => {
      if (options.onBefore?.() === false) {
        return Promise.reject(new Error("Request cancelled by onBefore"));
      }

      abortController.current = new AbortController();

      const cancelToken: CancelToken = {
        cancel: () => abortController.current?.abort(),
      };

      options.onCancelToken?.(cancelToken);
      options.onStart?.();

      const useFormData = hasFiles(data);

      let requestUrl = url;
      let requestData: FormData | string | undefined;
      let contentType: string | undefined;

      if (method === "get") {
        const [urlWithParams] = mergeDataIntoQueryString(method, url, data);
        requestUrl = urlWithParams;
      } else {
        if (useFormData) {
          requestData = objectToFormData(data);
        } else {
          requestData = JSON.stringify(data);
          contentType = "application/json";
        }
      }

      try {
        const httpResponse = await http.getClient().request({
          method,
          url: requestUrl,
          data: requestData,
          headers: {
            Accept: "application/json",
            ...(contentType ? { "Content-Type": contentType } : {}),
            ...options.headers,
          },
          signal: abortController.current.signal,
          onUploadProgress: (event: HttpProgressEvent) => {
            options.onProgress?.(event);
          },
        });

        const responseData = (
          httpResponse.data ? JSON.parse(httpResponse.data) : null
        ) as TResponse;

        if (httpResponse.status >= 200 && httpResponse.status < 300) {
          options.onSuccess?.(responseData);

          return {
            data: responseData,
            status: httpResponse.status,
            headers: httpResponse.headers,
          };
        }

        throw new HttpResponseError(
          `Request failed with status ${httpResponse.status}`,
          httpResponse,
        );
      } catch (error: unknown) {
        if (error instanceof HttpResponseError) {
          throw error;
        }

        if (
          error instanceof HttpCancelledError ||
          (error instanceof Error && error.name === "AbortError")
        ) {
          options.onCancel?.();
          throw new HttpCancelledError("Request was cancelled", url);
        }

        throw error;
      } finally {
        abortController.current = null;
        options.onFinish?.();
      }
    },
    [],
  );

  const cancel = useCallback(() => {
    if (abortController.current) {
      abortController.current.abort();
    }
  }, []);

  const submitMethods = useMemo(
    () => ({
      get: <TResponse = unknown>(
        url: string,
        data?: RequestData,
        options?: UseHttpClientOptions<TResponse>,
      ): Promise<HttpClientResponse<TResponse>> =>
        submit("get", url, data, options),
      post: <TResponse = unknown>(
        url: string,
        data?: RequestData,
        options?: UseHttpClientOptions<TResponse>,
      ): Promise<HttpClientResponse<TResponse>> =>
        submit("post", url, data, options),
      put: <TResponse = unknown>(
        url: string,
        data?: RequestData,
        options?: UseHttpClientOptions<TResponse>,
      ): Promise<HttpClientResponse<TResponse>> =>
        submit("put", url, data, options),
      patch: <TResponse = unknown>(
        url: string,
        data?: RequestData,
        options?: UseHttpClientOptions<TResponse>,
      ): Promise<HttpClientResponse<TResponse>> =>
        submit("patch", url, data, options),
      delete: <TResponse = unknown>(
        url: string,
        data?: RequestData,
        options?: UseHttpClientOptions<TResponse>,
      ): Promise<HttpClientResponse<TResponse>> =>
        submit("delete", url, data, options),
    }),
    [submit],
  );

  return {
    isHttpError,
    isHttpResponseError,
    submit,
    ...submitMethods,
    cancel,
  };
}
