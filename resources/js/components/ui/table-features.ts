import {
  columnVisibilityFeature,
  createSortedRowModel,
  metaHelper,
  rowPaginationFeature,
  rowSortingFeature,
  sortFn_alphanumeric,
  sortFn_basic,
  sortFn_datetime,
  sortFn_text,
  tableFeatures,
} from '@tanstack/react-table';

/**
 * The one feature set every table in the app registers. v9 only exposes an API
 * when its feature is present here, so this is the list of table APIs the
 * shared components are allowed to call:
 *
 * - `rowSortingFeature` — header sort toggles and `column.getIsSorted()`
 * - `rowPaginationFeature` — the `pagination` state ServerDataTable controls
 * - `columnVisibilityFeature` — `row.getVisibleCells()` in TableShell
 *
 * Both tables share it because neither row-model slot changes the other's
 * behavior: `sortedRowModel` is skipped under ServerDataTable's
 * `manualSorting`, and no `paginatedRowModel` slot is registered at all, so
 * `getRowModel()` never slices rows for either table.
 */
export const features = tableFeatures({
  columnVisibilityFeature,
  rowPaginationFeature,
  rowSortingFeature,
  sortedRowModel: createSortedRowModel(),
  /*
   * Columns declare no `sortFn`, so every one resolves through `'auto'`, and
   * auto only finds names registered here — an unregistered name silently
   * falls back to `basic` and sorts dates and text wrong.
   */
  sortFns: {
    alphanumeric: sortFn_alphanumeric,
    basic: sortFn_basic,
    datetime: sortFn_datetime,
    text: sortFn_text,
  },
  // Per-table column meta, replacing v8's global `ColumnMeta` declaration merging.
  columnMeta: metaHelper<{ className?: string }>(),
});

export type TableFeatures = typeof features;
