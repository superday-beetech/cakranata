
import { defineStore } from "pinia";
import { markRaw, type Component } from "vue";

export type OffcanvasPlacement =
    | "start"
    | "end"
    | "top"
    | "bottom";

export interface OffcanvasOptions {
    title?: string;
    component: Component;
    props?: Record<string, unknown>;
    placement?: OffcanvasPlacement;
    width?: string;
    height?: string;
    backdrop?: boolean | "static";
    scroll?: boolean;
    keyboard?: boolean;
}

interface OffcanvasState {
    isOpen: boolean;
    title: string;
    component: Component | null;
    props: Record<string, unknown>;
    placement: OffcanvasPlacement;
    width: string;
    height: string;
    backdrop: boolean | "static";
    scroll: boolean;
    keyboard: boolean;
}

export const useOffcanvasStore = defineStore("offcanvas", {
    state: (): OffcanvasState => ({
        isOpen: false,
        title: "",
        component: null,
        props: {},
        placement: "end",
        width: "480px",
        height: "50vh",
        backdrop: true,
        scroll: false,
        keyboard: true,
    }),

    actions: {
        open(options: OffcanvasOptions) {
            this.title = options.title ?? "";
            this.component = markRaw(options.component);
            this.props = options.props ?? {};

            this.placement = options.placement ?? "end";
            this.width = options.width ?? "480px";
            this.height = options.height ?? "50vh";

            this.backdrop = options.backdrop ?? true;
            this.scroll = options.scroll ?? false;
            this.keyboard = options.keyboard ?? true;

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
        },
    },
});
