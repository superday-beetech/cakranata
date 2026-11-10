import { onBeforeUnmount, ref } from "vue";

function getErrorMessage(error: unknown): string {
  const err = error as {
    response?: {
      data?: {
        message?: string | string[];
      };
    };
    message?: string;
  };

  const message = err?.response?.data?.message;

  if (Array.isArray(message)) {
    return message.join(", ");
  }

  return message || err?.message || "Terjadi kesalahan.";
}

export function usePosFeedback() {
  const message = ref("");
  const errorMessage = ref("");

  let messageTimer: ReturnType<typeof setTimeout> | undefined;

  function clearMessages(): void {
    clearTimeout(messageTimer);
    messageTimer = undefined;

    message.value = "";
    errorMessage.value = "";
  }

  function showError(error: unknown): void {
    errorMessage.value = getErrorMessage(error);
    message.value = "";
  }

  function showSuccess(text: string): void {
    clearTimeout(messageTimer);

    message.value = text;
    errorMessage.value = "";

    messageTimer = setTimeout(() => {
      message.value = "";
      messageTimer = undefined;
    }, 5000);
  }

  onBeforeUnmount(() => {
    clearTimeout(messageTimer);
    messageTimer = undefined;
  });

  return {
    message,
    errorMessage,
    clearMessages,
    showError,
    showSuccess,
  };
}
