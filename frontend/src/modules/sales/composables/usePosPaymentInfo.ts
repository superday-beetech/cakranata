import { reactive } from "vue";

/**
 * Info rekening/QRIS yang ditampilkan di panel pembayaran.
 * TODO: isi dari pengaturan integrasi pembayaran bila sudah tersedia di backend.
 */
export function usePosPaymentInfo() {
  return reactive({
    bankName: "",
    accountNumber: "",
    accountHolder: "",
    qrisImageUrl: null as string | null,
  });
}
