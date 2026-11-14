
import { defineStore } from "pinia";
import { markRaw, type Component } from "vue";

export type ModalSize = "sm" | "default" | "lg" | "xl" | "fullscreen";

export interface ModalOptions {
    title?: string;
    component: Component;
    props?: Record<string, unknown>;
    size?: ModalSize;
    centered?: boolean;
    scrollable?: boolean;
    backdrop?: boolean | "static";
    keyboard?: boolean;
    onSelect?: (value: unknown) => void;
}

interface ModalState {
    isOpen: boolean;
    title: string;
    component: Component | null;
    props: Record<string, unknown>;
    size: ModalSize;
    centered: boolean;
    scrollable: boolean;
    backdrop: boolean | "static";
    keyboard: boolean;
    onSelect: ((value: unknown) => void) | null;
}

export const useModalStore = defineStore("modal", {
    state: (): ModalState => ({
        isOpen: false,
        title: "",
        component: null,
        props: {},
        size: "default",
        centered: false,
        scrollable: false,
        backdrop: true,
        keyboard: true,
        onSelect:null
    }),

    actions: {
        open(options: ModalOptions) {
            this.title = options.title ?? "";
            this.component = markRaw(options.component);
            this.props = options.props ?? {};
            this.size = options.size ?? "default";
            this.centered = options.centered ?? false;
            this.scrollable = options.scrollable ?? false;
            this.backdrop = options.backdrop ?? true;
            this.keyboard = options.keyboard ?? true;
            this.onSelect = options.onSelect ?? null;

            this.isOpen = true;
        },

        close() {
            this.isOpen = false;
        },

        reset() {
            this.isOpen = false;
            this.title = "";
            this.component = null;
            this.props = {};
            this.onSelect = null;
        },

    },
});
