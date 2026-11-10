import { ref } from "vue";

export function usePosShortcutHelp() {
  const shortcutHelp = ref(false);

  function toggleShortcutHelp(): void {
    shortcutHelp.value = !shortcutHelp.value;
  }

  function closeShortcutHelp(): void {
    shortcutHelp.value = false;
  }

  function setShortcutHelp(visible: boolean): void {
    shortcutHelp.value = visible;
  }

  return {
    shortcutHelp,
    toggleShortcutHelp,
    closeShortcutHelp,
    setShortcutHelp,
  };
}
