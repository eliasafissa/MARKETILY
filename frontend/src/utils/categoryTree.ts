/**
 * Build a child lookup map from a flat category list.
 * Uses oranos_parent_id (the Oranos tree we populated) rather than parent_id,
 * because many Oranos children have parent_id=null in our local DB.
 */
export interface TreeCategory {
  id: number;
  oranos_parent_id?: number | null;
  parent_id?: number | null;
  children?: TreeCategory[];
  [key: string]: any;
}

export function buildChildMap(all: TreeCategory[]): Map<number, TreeCategory[]> {
  const map = new Map<number, TreeCategory[]>();
  for (const c of all) {
    const parentId = c.oranos_parent_id ?? c.parent_id ?? null;
    if (parentId == null) continue;
    const list = map.get(parentId) ?? [];
    list.push(c);
    map.set(parentId, list);
  }
  return map;
}

export function getChildren(
  all: TreeCategory[],
  current: TreeCategory,
): TreeCategory[] {
  // Try oranos_parent_id first
  const map = buildChildMap(all);
  const direct = map.get(current.id) ?? [];
  if (direct.length > 0) return direct;

  // Fallback: some categories use Laravel's parent_id
  return all.filter((c) => c.parent_id === current.id);
}
