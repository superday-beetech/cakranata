import { ref } from "vue";

interface UsePosDisplayOptions {
  notifyWarning: (message: string) => void;
}

export function usePosDisplay(options: UsePosDisplayOptions) {
  const isFullscreen = ref(false);
  const compactHeader = ref(false);

  // Muat preferensi mode ringkas.
  try {
    compactHeader.value = localStorage.getItem("pos.compact") === "1";
  } catch {
    // Abaikan jika localStorage tidak tersedia.
  }

  function syncFullscreen(): void {
    isFullscreen.value = !!document.fullscreenElement;
  }

  async function toggleFullscreen(): Promise<void> {
    try {
      if (document.fullscreenElement) {
        await document.exitFullscreen();
      } else {
        await document.documentElement.requestFullscreen();
      }
    } catch {
      options.notifyWarning("Layar penuh tidak didukung atau diblokir browser.");
    }
  }

  function toggleCompact(): void {
    compactHeader.value = !compactHeader.value;

    try {
      localStorage.setItem("pos.compact", compactHeader.value ? "1" : "0");
    } catch {
      // Abaikan jika localStorage tidak tersedia.
    }
  }

  return {
    isFullscreen,
    compactHeader,
    syncFullscreen,
    toggleFullscreen,
    toggleCompact,
  };
}
