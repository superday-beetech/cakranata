import { shallowRef, watch, type Ref } from "vue";

/**
 * Clone yang aman untuk data reaktif Vue.
 * - Unwrap proxy dulu via JSON (data template murni JSON-safe)
 * - Tidak mengandung fungsi / Date / Map / Set / File
 */
function safeClone<T>(value: T): T {
  try {
    return JSON.parse(JSON.stringify(value)) as T;
  } catch {
    // fallback: kalau gagal, kembalikan apa adanya (jarang terjadi)
    return value;
  }
}

export function useHistory<T>(source: Ref<T>, limit = 80) {
  const past = shallowRef<T[]>([]);
  const future = shallowRef<T[]>([]);
  let skip = false;

  watch(
    source,
    (val) => {
      if (skip) {
        skip = false;
        return;
      }
      past.value.push(safeClone(val));
      if (past.value.length > limit) past.value.shift();
      future.value = [];
    },
    { deep: true },
  );

  function undo() {
    if (!past.value.length) return;
    future.value.push(safeClone(source.value));
    skip = true;
    source.value = past.value.pop()!;
  }

  function redo() {
    if (!future.value.length) return;
    past.value.push(safeClone(source.value));
    skip = true;
    source.value = future.value.pop()!;
  }

  function reset() {
    past.value = [];
    future.value = [];
  }

  return {
    undo,
    redo,
    reset,
    canUndo: () => past.value.length > 0,
    canRedo: () => future.value.length > 0,
  };
}
