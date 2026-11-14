import { computed, ref, toValue, type Ref } from "vue";
import type { ApplyAction, ScanFilter, ScanItem } from "../types/route-registry";

type MaybeRefOrGetter<T> = T | Ref<T> | (() => T);

export function useScanSelection(items: MaybeRefOrGetter<readonly ScanItem[]>, filter: Ref<ScanFilter>, search: Ref<string>, action: Ref<ApplyAction>) {
  const itemsRef = computed<readonly ScanItem[]>(() => toValue(items));
  const selected = ref<ReadonlySet<string>>(new Set<string>());

  function isRegistered(item: ScanItem): boolean {
    return item.state === "registered" || Number(item.registry?.id ?? 0) > 0;
  }

  const visibleItems = computed<ScanItem[]>(() => {
    const q = search.value.trim().toLowerCase();
    return itemsRef.value.filter((item) => {
      const registered = isRegistered(item);
      if (filter.value === "new" && registered) return false;
      if (filter.value === "registered" && !registered) return false;
      if (!q) return true;
      const haystack = [item.module, item.controller, item.method, item.endpoint, item.url_route ?? ""].join(" ").toLowerCase();
      return haystack.includes(q);
    });
  });

  const eligibleForAction = computed<ScanItem[]>(() =>
    visibleItems.value.filter((item) => {
      if (!selected.value.has(item.endpoint)) return false;
      const registered = isRegistered(item);
      if (action.value === "insert") return !registered;
      if (action.value === "update") return registered;
      if (action.value === "delete") return registered;
      return false;
    }),
  );

  const selectedCount = computed(() => selected.value.size);
  const eligibleCount = computed(() => eligibleForAction.value.length);

  const allVisibleSelected = computed(() => {
    const visible = visibleItems.value;
    if (visible.length === 0) return false;
    return visible.every((i) => selected.value.has(i.endpoint));
  });

  function toggle(endpoint: string): void {
    const next = new Set(selected.value);
    if (next.has(endpoint)) next.delete(endpoint);
    else next.add(endpoint);
    selected.value = next;
  }

  function setAllVisible(checked: boolean): void {
    const next = new Set(selected.value);
    for (const item of visibleItems.value) {
      if (checked) next.add(item.endpoint);
      else next.delete(item.endpoint);
    }
    selected.value = next;
  }

  function selectAllVisible(): void {
    setAllVisible(true);
  }
  function clear(): void {
    selected.value = new Set();
  }

  return {
    selected,
    visibleItems,
    eligibleForAction,
    selectedCount,
    eligibleCount,
    allVisibleSelected,
    isRegistered,
    toggle,
    setAllVisible,
    selectAllVisible,
    clear,
  };
}
