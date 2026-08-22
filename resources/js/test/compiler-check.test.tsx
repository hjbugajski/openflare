import { describe, expect, it } from 'vitest';

import { ServerDataTable } from '@/components/server-data-table';
import { TableShell } from '@/components/ui/table-shell';

/*
 * Guards test/production parity: vitest must compile with the React Compiler
 * (see vitest.config.ts) so component tests exercise the same memoization
 * behavior as production builds. The checks-table pagination bug only
 * reproduced under compiled output. `$[` is the compiler's memo-cache access.
 */

// A local fixture rather than a production component: props, a conditional and
// a list render are squarely inside what the compiler memoizes, so the positive
// assertion tracks the transform instead of some other file's current shape.
function CompiledFixture({ items, label }: { items: string[]; label?: string }) {
  return (
    <ul>
      {label ? <li>{label}</li> : null}
      {items.map((item) => (
        <li key={item}>{item}</li>
      ))}
    </ul>
  );
}

describe('react compiler transform', () => {
  it('applies the react compiler to components', () => {
    expect(CompiledFixture.toString()).toContain('$[');
  });

  it('respects "use no memo" on table-shell', () => {
    expect(TableShell.toString()).not.toContain('$[');
  });

  /*
   * Load-bearing: the compiler bails out of `ServerDataTable` because of
   * `useTable`. Were it compiled, the `TableShell` element it builds would
   * be memo-cached on the referentially stable table instance and table-shell's
   * own directive would no longer save it.
   */
  it('leaves server-data-table uncompiled', () => {
    expect(ServerDataTable.toString()).not.toContain('$[');
  });
});
