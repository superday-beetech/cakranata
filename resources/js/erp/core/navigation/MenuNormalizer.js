export function normalizeMenus(items = []) {
  if (!Array.isArray(items)) {
    return [];
  }

  return items
    .filter((item) => Number(item.is_active) === 1)
    .map((item) => ({
      ...item,
      is_divider: item.url === "divider" || Number(item.is_divider) === 1,
      children: normalizeMenus(item.children ?? []),
    }))
    .sort((a, b) => {
      return Number(a.sequence || 0) - Number(b.sequence || 0);
    });
}
