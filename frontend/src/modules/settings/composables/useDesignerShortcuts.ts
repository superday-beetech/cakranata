import { onMounted, onUnmounted, type Ref } from "vue";
import { uuidv4 } from "@/utils/uuid";
import type { PrintElement } from "../types/printTemplate";

interface Options {
  elements: Ref<PrintElement[]>;
  selectedId: Ref<string | null>;
  logoSelected?: Ref<boolean>;
  onDeleteLogo?: () => void;
  onDelete: (id: string) => void;
  onSelect: (id: string | null) => void;
  onUndo: () => void;
  onRedo: () => void;
}

export function useDesignerShortcuts(opts: Options) {
  let clipboard: PrintElement | null = null;

  function isTyping(e: KeyboardEvent) {
    const t = e.target as HTMLElement;
    return t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA" || t.tagName === "SELECT" || t.isContentEditable);
  }

  function handler(e: KeyboardEvent) {
    if (isTyping(e)) return;
    const meta = e.ctrlKey || e.metaKey;

    if (meta && e.key.toLowerCase() === "z") {
      e.preventDefault();
      e.shiftKey ? opts.onRedo() : opts.onUndo();
      return;
    }
    if (meta && e.key.toLowerCase() === "d" && opts.selectedId.value) {
      e.preventDefault();
      const src = opts.elements.value.find((x) => x.id === opts.selectedId.value);
      if (!src) return;
      const clone = structuredClone(src) as PrintElement;
      clone.id = uuidv4();
      clone.x += 5;
      clone.y += 5;
      opts.elements.value.push(clone);
      opts.onSelect(clone.id);
      return;
    }
    if (meta && e.key.toLowerCase() === "c" && opts.selectedId.value) {
      const src = opts.elements.value.find((x) => x.id === opts.selectedId.value);
      if (src) clipboard = structuredClone(src) as PrintElement;
      return;
    }
    if (meta && e.key.toLowerCase() === "v" && clipboard) {
      e.preventDefault();
      const clone = structuredClone(clipboard) as PrintElement;
      clone.id = uuidv4();
      clone.x += 10;
      clone.y += 10;
      opts.elements.value.push(clone);
      opts.onSelect(clone.id);
      return;
    }
    if (e.key === "Delete" || e.key === "Backspace") {
      if (opts.logoSelected?.value && opts.onDeleteLogo) {
        e.preventDefault();
        opts.onDeleteLogo();
        return;
      }
      if (opts.selectedId.value) {
        e.preventDefault();
        opts.onDelete(opts.selectedId.value);
      }
      return;
    }
    if (e.key === "Escape") {
      if (opts.logoSelected?.value) opts.logoSelected.value = false;
      else opts.onSelect(null);
      return;
    }
    if (opts.selectedId.value && ["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight"].includes(e.key)) {
      e.preventDefault();
      const step = e.shiftKey ? 5 : 1;
      const el = opts.elements.value.find((x) => x.id === opts.selectedId.value);
      if (!el || el.props.locked) return;
      if (e.key === "ArrowUp") el.y -= step;
      if (e.key === "ArrowDown") el.y += step;
      if (e.key === "ArrowLeft") el.x -= step;
      if (e.key === "ArrowRight") el.x += step;
    }
  }

  onMounted(() => window.addEventListener("keydown", handler));
  onUnmounted(() => window.removeEventListener("keydown", handler));
}
