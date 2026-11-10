
import { computed, ref } from "vue";
import { defineStore } from "pinia";
import axios from "axios";

import api from "@/api/client";

const BASE_URL = "/organization/organization-context/";

export interface AvailableOrganization {
    membership_id: number;
    organization_id: number;
    user_id: number;
    unit_id: number | null;
    membership_role: string;
    is_default: boolean;
    organization_code: string;
    organization_name: string;
    organization_type: string;
    timezone: string;
    currency: string;
    unit_name: string | null;
}

export interface OrganizationContext {
    organization: {
        id: number;
        code: string;
        name: string;
        organization_type: string;
        timezone: string;
        currency: string;
    };
    unit: {
        id: number;
        code: string;
        name: string;
        unit_type: string;
    } | null;
    membership: {
        id: number;
        user_id: number;
        role: string;
        status: string;
        is_default: boolean;
    };
}

interface ApiResponse<T> {
    status: string;
    error: boolean;
    message: string | string[];
    data: T;
}

function toBoolean(value: unknown): boolean {
    return (
        value === true ||
        value === 1 ||
        value === "1"
    );
}

function getErrorMessage(error: unknown): string {
    if (axios.isAxiosError<ApiResponse<unknown>>(error)) {
        const message = error.response?.data?.message;

        if (Array.isArray(message)) {
            return message.join(", ");
        }

        if (message) {
            return message;
        }
    }

    if (error instanceof Error) {
        return error.message;
    }

    return "Terjadi kesalahan saat memproses organisasi.";
}

export const useOrganizationContextStore = defineStore(
    "organizationContext",
    () => {
        // -------------------------------------------------
        // STATE
        // -------------------------------------------------

        const availableOrganizations =
            ref<AvailableOrganization[]>([]);

        const activeContext =
            ref<OrganizationContext | null>(null);

        const loading = ref(false);
        const switching = ref(false);
        const initialized = ref(false);
        const errorMessage = ref<string | null>(null);

        let initializePromise: Promise<void> | null = null;

        // -------------------------------------------------
        // GETTERS
        // -------------------------------------------------

        const activeOrganization = computed(
            () => activeContext.value?.organization ?? null
        );

        const activeUnit = computed(
            () => activeContext.value?.unit ?? null
        );

        const activeMembership = computed(
            () => activeContext.value?.membership ?? null
        );

        const membershipRole = computed(
            () => activeMembership.value?.role ?? null
        );

        const hasContext = computed(
            () => activeContext.value !== null
        );

        const hasOrganizations = computed(
            () => availableOrganizations.value.length > 0
        );

        const needsOrganizationSelection = computed(
            () => hasOrganizations.value && !hasContext.value
        );

        const isOrganizationMember = computed(
            () => hasContext.value
        );

        // -------------------------------------------------
        // NORMALIZATION
        // -------------------------------------------------

        function normalizeAvailableOrganization(
            item: AvailableOrganization
        ): AvailableOrganization {
            return {
                ...item,
                membership_id: Number(item.membership_id),
                organization_id: Number(item.organization_id),
                user_id: Number(item.user_id),
                unit_id:
                    item.unit_id === null
                        ? null
                        : Number(item.unit_id),
                is_default: toBoolean(item.is_default),
            };
        }

        function normalizeContext(
            context: OrganizationContext
        ): OrganizationContext {
            return {
                organization: {
                    ...context.organization,
                    id: Number(context.organization.id),
                },

                unit: context.unit
                    ? {
                        ...context.unit,
                        id: Number(context.unit.id),
                    }
                    : null,

                membership: {
                    ...context.membership,
                    id: Number(context.membership.id),
                    user_id: Number(context.membership.user_id),
                    is_default: toBoolean(
                        context.membership.is_default
                    ),
                },
            };
        }

        // -------------------------------------------------
        // API RESPONSE
        // -------------------------------------------------

        function getPayload<T>(response: unknown): T {
            const result = (
                response as {
                    data?: ApiResponse<T>;
                }
            )?.data;

            if (!result || result.error) {
                const message = result?.message;

                throw new Error(
                    Array.isArray(message)
                        ? message.join(", ")
                        : message ?? "Request API gagal."
                );
            }

            return result.data;
        }

        // -------------------------------------------------
        // AVAILABLE ORGANIZATIONS
        // -------------------------------------------------

        async function fetchAvailableOrganizations(): Promise<void> {
            const response = await api.get(
                `${BASE_URL}index-available`
            );

            const data = getPayload<AvailableOrganization[]>(
                response
            );

            availableOrganizations.value = Array.isArray(data)
                ? data.map(normalizeAvailableOrganization)
                : [];
        }

        // -------------------------------------------------
        // CURRENT CONTEXT
        // -------------------------------------------------

        async function fetchCurrentContext(): Promise<void> {
            const response = await api.get(
                `${BASE_URL}/index-context`
            );

            const data = getPayload<OrganizationContext | null>(
                response
            );

            activeContext.value = data
                ? normalizeContext(data)
                : null;
        }

        // -------------------------------------------------
        // SWITCH ORGANIZATION
        // -------------------------------------------------

        async function switchOrganization(
            organizationId: number
        ): Promise<OrganizationContext> {
            const id = Number(organizationId);

            if (!Number.isInteger(id) || id <= 0) {
                throw new Error("Organisasi tidak valid.");
            }

            const available = availableOrganizations.value.some(
                (item) => item.organization_id === id
            );

            if (!available) {
                throw new Error(
                    "Organisasi tidak tersedia pada akun Anda."
                );
            }

            if (switching.value) {
                throw new Error(
                    "Pergantian organisasi sedang diproses."
                );
            }

            switching.value = true;
            errorMessage.value = null;

            try {
                const response = await api.post(
                    `${BASE_URL}/switch-organization`,
                    {
                        organization_id: id,
                    }
                );

                const data = getPayload<OrganizationContext>(
                    response
                );

                if (!data?.organization || !data?.membership) {
                    throw new Error(
                        "Response konteks organisasi tidak valid."
                    );
                }

                activeContext.value = normalizeContext(data);

                return activeContext.value;
            } catch (error) {
                errorMessage.value = getErrorMessage(error);
                throw error;
            } finally {
                switching.value = false;
            }
        }

        // -------------------------------------------------
        // CLEAR CONTEXT
        // -------------------------------------------------

        async function clearContext(): Promise<void> {
            await api.post(`${BASE_URL}/clear-context`);

            activeContext.value = null;
        }

        // -------------------------------------------------
        // INITIALIZE
        // -------------------------------------------------

        async function initialize(force = false): Promise<void> {
            if (initialized.value && !force) {
                return;
            }

            if (initializePromise) {
                return initializePromise;
            }

            initializePromise = (async () => {
                loading.value = true;
                errorMessage.value = null;

                try {
                    // Ambil organisasi yang tersedia.
                    await fetchAvailableOrganizations();

                    // Pulihkan konteks dari session backend.
                    await fetchCurrentContext();

                    // Jika belum ada konteks, tentukan pilihan otomatis.
                    if (!activeContext.value) {
                        const organizations =
                            availableOrganizations.value;

                        const defaults = organizations.filter(
                            (item) => item.is_default
                        );

                        let selectedId: number | null = null;

                        if (organizations.length === 1) {
                            selectedId =
                                organizations[0].organization_id;
                        } else if (defaults.length === 1) {
                            selectedId =
                                defaults[0].organization_id;
                        }

                        if (selectedId !== null) {
                            await switchOrganization(selectedId);
                        }
                    }

                    initialized.value = true;
                } catch (error) {
                    errorMessage.value = getErrorMessage(error);
                    initialized.value = false;
                    throw error;
                } finally {
                    loading.value = false;
                    initializePromise = null;
                }
            })();

            return initializePromise;
        }

        // -------------------------------------------------
        // RESET
        // -------------------------------------------------

        function reset(): void {
            availableOrganizations.value = [];
            activeContext.value = null;

            loading.value = false;
            switching.value = false;
            initialized.value = false;

            errorMessage.value = null;
            initializePromise = null;
        }

        return {
            // State
            availableOrganizations,
            activeContext,
            loading,
            switching,
            initialized,
            errorMessage,

            // Getters
            activeOrganization,
            activeUnit,
            activeMembership,
            membershipRole,
            hasContext,
            hasOrganizations,
            needsOrganizationSelection,
            isOrganizationMember,

            // Actions
            fetchAvailableOrganizations,
            fetchCurrentContext,
            switchOrganization,
            clearContext,
            initialize,
            reset,
        };
    }
);
