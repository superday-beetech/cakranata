import { ref, shallowRef, readonly, onUnmounted, type Ref } from "vue";
import { api } from "@/services/axios";
import type { AxiosRequestConfig, AxiosError } from "axios";

export interface UseAxiosOptions<T> {
  immediate?: boolean;
  initialValue?: T;
  onSuccess?: (data: T) => void;
  onError?: (error: AxiosError) => void;
}

export function useAxios<T>(url: string | (() => string), config: AxiosRequestConfig = {}, options: UseAxiosOptions<T> = {}) {
  const { immediate = true, initialValue = null, onSuccess, onError } = options;

  const data = shallowRef<T | null>(initialValue as T) as Ref<T | null>;
  const isLoading = ref<boolean>(false);
  const error = ref<AxiosError | null>(null);

  // Controller untuk membatalkan request jika komponen di-unmount
  let abortController = new AbortController();

  const execute = async (customConfig?: AxiosRequestConfig) => {
    // Batalkan request sebelumnya jika masih berlangsung
    abortController.abort();
    abortController = new AbortController();

    isLoading.value = true;
    error.value = null;

    const targetUrl = typeof url === "function" ? url() : url;

    try {
      const response = await api.request<T>({
        url: targetUrl,
        ...config,
        ...customConfig,
        signal: abortController.signal,
      });

      data.value = response.data;
      if (onSuccess) onSuccess(response.data);
      return response.data;
    } catch (err: any) {
      if (err.name !== "CanceledError" && err.code !== "ERR_CANCELED") {
        error.value = err;
        if (onError) onError(err);
      }
      throw err;
    } finally {
      if (!abortController.signal.aborted) {
        isLoading.value = false;
      }
    }
  };

  // Cleanup memori saat komponen dihancurkan
  onUnmounted(() => {
    abortController.abort();
  });

  if (immediate) {
    execute();
  }

  return {
    data: readonly(data),
    isLoading: readonly(isLoading),
    error: readonly(error),
    execute,
  };
}
