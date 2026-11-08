// Sentuhan visual untuk POS: jam + sapaan, dan kedip keranjang saat belanja bertambah.
// Murni tampilan. Tidak mengubah state transaksi apa pun.
import { ref, computed, watch, toValue, onMounted, onBeforeUnmount, type MaybeRefOrGetter } from "vue";

export function usePosDelight(subtotal: MaybeRefOrGetter<number>) {
  // ---- Jam & sapaan ----
  const now = ref(new Date());
  let clockTimer: ReturnType<typeof setInterval> | undefined;
  let flashTimer: ReturnType<typeof setTimeout> | undefined;

  onMounted(() => {
    clockTimer = setInterval(() => {
      now.value = new Date();
    }, 30000);
  });

  onBeforeUnmount(() => {
    if (clockTimer) clearInterval(clockTimer);
    if (flashTimer) clearTimeout(flashTimer);
  });

  const clockTime = computed(() => now.value.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" }).replace(".", ":"));

  const greeting = computed(() => {
    const h = now.value.getHours();
    if (h >= 4 && h < 11) return "Selamat pagi, semangat melayani!";
    if (h >= 11 && h < 15) return "Selamat siang, jangan lupa minum air.";
    if (h >= 15 && h < 18) return "Selamat sore, tinggal sedikit lagi.";
    return "Selamat malam, terima kasih sudah bertugas.";
  });

  // ---- Kedip keranjang saat subtotal naik (produk ditambah / qty naik) ----
  const cartFlash = ref(false);

  watch(
    () => Number(toValue(subtotal)) || 0,
    (next, prev) => {
      if (next <= prev) return;
      cartFlash.value = false;
      requestAnimationFrame(() => {
        cartFlash.value = true;
        if (flashTimer) clearTimeout(flashTimer);
        flashTimer = setTimeout(() => {
          cartFlash.value = false;
        }, 650);
      });
    },
  );

  return { clockTime, greeting, cartFlash };
}
