import { describe, expect, it } from 'vitest';

import { Badge } from '@/components/ui/badge';
import { TableShell } from '@/components/ui/table-shell';

/*
 * Guards test/production parity: vitest must compile with the React Compiler
 * (see vitest.config.ts) so component tests exercise the same memoization
 * behavior as production builds. The checks-table pagination bug only
 * reproduced under compiled output. `$[` is the compiler's memo-cache access.
 */
describe('react compiler transform', () => {
  it('applies the react compiler to components', () => {
    expect(Badge.toString()).toContain('$[');
  });

  it('respects "use no memo" on table-shell', () => {
    expect(TableShell.toString()).not.toContain('$[');
  });
});
