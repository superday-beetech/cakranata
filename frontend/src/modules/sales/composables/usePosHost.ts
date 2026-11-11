import type { Component } from "vue";

import { useModalStore, type ModalSize } from "@/composables/modal";
import { useOffcanvasStore } from "@/composables/offcanvas";
import { modal } from "@/utils/modal";
import { offcanvas } from "@/utils/offcanvas";
import { notify as globalNotify } from "@/utils/notification";
import pinia from "@/stores";

export function usePosHost() {
  function openOffCanvas(component: Component, options: { title: string; width?: string; props?: Record<string, unknown> }): void {
    offcanvas.open({
      component,
      title: options.title,
      width: options.width ?? "480px",
      props: options.props,
    });
  }

  function openModal(component: Component, options: { title: string; size?: ModalSize; props?: Record<string, unknown> }): void {
    modal.open({
      component,
      title: options.title,
      size: options.size ?? "default",
      centered: true,
      scrollable: true,
      props: options.props,
    });
  }

  function notify(type: "success" | "error" | "info" | "warning", text: string): void {
    globalNotify[type](text);
  }

  /** True bila modal atau offcanvas global sedang terbuka. */
  function isOpen(): boolean {
    return useModalStore(pinia).isOpen || useOffcanvasStore(pinia).isOpen;
  }

  return { openOffCanvas, openModal, notify, isOpen };
}
