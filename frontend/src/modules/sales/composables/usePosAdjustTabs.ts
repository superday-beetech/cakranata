import { nextTick, ref } from "vue";

import type { PosFocusTarget } from "./usePosFocus";

export type AdjustTab = "discount" | "charges" | "notes" | "held";

export interface AdjustTabItem {
  id: AdjustTab;
  label: string;
  fullLabel?: string;
  icon: string;
  shortcut?: string;
}

interface UsePosAdjustTabsOptions {
  focusSection: (section: PosFocusTarget) => void;
}

export function usePosAdjustTabs(options: UsePosAdjustTabsOptions) {
  const adjustTab = ref<AdjustTab>("discount");

  const adjustTabItems: AdjustTabItem[] = [
    { id: "discount", label: "Diskon", icon: "ti ti-discount-2", shortcut: "F4" },
    { id: "charges", label: "Biaya", fullLabel: "Biaya tambahan", icon: "ti ti-package", shortcut: "F5" },
    { id: "notes", label: "Catatan", icon: "ti ti-notes" },
    { id: "held", label: "Ditahan", icon: "ti ti-player-pause" },
  ];

  function onTabKeydown(event: KeyboardEvent, index: number): void {
    const last = adjustTabItems.length - 1;
    let next: number;

    switch (event.key) {
      case "ArrowRight":
        next = index === last ? 0 : index + 1;
        break;

      case "ArrowLeft":
        next = index === 0 ? last : index - 1;
        break;

      case "Home":
        next = 0;
        break;

      case "End":
        next = last;
        break;

      default:
        return;
    }

    event.preventDefault();
    adjustTab.value = adjustTabItems[next].id;

    void nextTick(() => {
      document.getElementById(`pos-tab-${adjustTabItems[next].id}`)?.focus();
    });
  }

  async function openAdjustTab(tab: AdjustTab, section: PosFocusTarget): Promise<void> {
    adjustTab.value = tab;
    await nextTick();
    options.focusSection(section);
  }

  return {
    adjustTab,
    adjustTabItems,
    onTabKeydown,
    openAdjustTab,
  };
}
