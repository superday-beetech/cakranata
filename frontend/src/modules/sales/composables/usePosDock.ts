import { reactive } from "vue";

export type DockEdge = "left" | "right" | "top" | "bottom";

export interface DockState {
  visible: boolean;
  open: boolean;
  edge: DockEdge;
  offset: number;
}

interface SavedDockState {
  visible?: boolean;
  edge?: DockEdge;
  offset?: number;
}

const DOCK_KEY = "pos.dock.v2";

const DOCK_EDGES: DockEdge[] = ["left", "right", "top", "bottom"];

function isDockEdge(value: unknown): value is DockEdge {
  return typeof value === "string" && DOCK_EDGES.includes(value as DockEdge);
}

function loadDock(): SavedDockState {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(DOCK_KEY) ?? "{}");

    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      return {};
    }

    return parsed as SavedDockState;
  } catch {
    return {};
  }
}

export function usePosDock() {
  const saved = loadDock();

  // Default: terlihat, terlipat, posisi kanan, offset tengah.
  const dock = reactive<DockState>({
    visible: saved.visible !== false,
    open: false,
    edge: isDockEdge(saved.edge) ? saved.edge : "right",
    offset: typeof saved.offset === "number" && Number.isFinite(saved.offset) ? Math.min(92, Math.max(8, saved.offset)) : 50,
  });

  function saveDock(): void {
    try {
      localStorage.setItem(
        DOCK_KEY,
        JSON.stringify({
          visible: dock.visible,
          edge: dock.edge,
          offset: dock.offset,
        }),
      );
    } catch {
      // Penyimpanan lokal tidak boleh mengganggu operasional POS.
    }
  }

  function toggleDock(): void {
    dock.open = !dock.open;
  }

  function moveDock(edge: DockEdge, offset = 50): void {
    if (!isDockEdge(edge)) return;

    dock.edge = edge;
    dock.offset = offset;

    saveDock();
  }

  function toggleDockVisibility(): void {
    dock.visible = !dock.visible;
    saveDock();
  }

  return {
    dock,
    toggleDock,
    moveDock,
    toggleDockVisibility,
  };
}
