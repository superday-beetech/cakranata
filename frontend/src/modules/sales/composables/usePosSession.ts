import { computed } from "vue";

import { useAuthStore } from "@/stores/auth";
import { useMenuStore } from "@/stores/menu";
import { useOrganizationContextStore } from "@/modules/organization/stores/organizationContextStore";

interface UsePosSessionOptions {
  notifyWarning: (message: string) => void;
}

export interface SignOutResult {
  success: boolean;
  error: string | null;
}

// Urutan prioritas nama kasir. Hanya username/email yang dijamin ada di AuthUser.
const CASHIER_NAME_FIELDS = ["full_name", "name", "display_name", "username", "email"] as const;

function textOf(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

/** Identitas kasir, konteks organisasi aktif, dan sign-out. */
export function usePosSession(options: UsePosSessionOptions) {
  const auth = useAuthStore();
  const menuStore = useMenuStore();
  const orgContext = useOrganizationContextStore();

  const cashierName = computed(() => {
    const user = auth.user as Record<string, unknown> | null;

    if (!user) return "";

    for (const field of CASHIER_NAME_FIELDS) {
      const value = textOf(user[field]);

      if (value) return value;
    }

    return "";
  });

  const cashierTitle = computed(() => (orgContext.membershipRole ? `Peran: ${orgContext.membershipRole}` : "Kasir yang sedang aktif"));

  const workspaceLabel = computed(() => [orgContext.activeOrganization?.name, orgContext.activeUnit?.name].filter(Boolean).join(" · "));

  // Konteks organisasi ada di sesi server (dipakai semua tab), jadi dicek ulang saat tab kembali aktif.
  let lastOrganizationId: number | null = null;

  const currentOrganizationId = (): number | null => orgContext.activeOrganization?.id ?? null;

  function rememberOrganization(): void {
    lastOrganizationId = currentOrganizationId();
  }

  async function syncOrganization(): Promise<void> {
    try {
      await orgContext.fetchCurrentContext();

      const id = currentOrganizationId();

      if (lastOrganizationId !== null && id !== lastOrganizationId) {
        options.notifyWarning("Organisasi aktif berubah di tab lain. Muat ulang halaman kasir sebelum melanjutkan transaksi.");
      }

      lastOrganizationId = id;
    } catch {
      // Abaikan; jaringan bisa sedang putus.
    }
  }

  async function signOut(): Promise<SignOutResult> {
    const success = await auth.logout();

    if (success) {
      menuStore.clear();
      window.location.href = "/login"; // atau window.location.reload();
    }

    return { success, error: success ? null : auth.error ? String(auth.error) : null };
  }

  return {
    cashierName,
    cashierTitle,
    workspaceLabel,
    rememberOrganization,
    syncOrganization,
    signOut,
  };
}
