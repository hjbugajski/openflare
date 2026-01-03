import { useCallback, useEffect, useRef, useState } from 'react';

import { Link, router } from '@inertiajs/react';

import { IconMinus } from '@/components/icons/minus';
import { IconPlus } from '@/components/icons/plus';
import { Button } from '@/components/ui/button';
import { home, logout } from '@/routes';
import { index as monitorsIndex } from '@/routes/monitors';
import { index } from '@/routes/notifiers';
import { show } from '@/routes/settings';

const FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const NAV_ITEMS = [
  { href: monitorsIndex().url, label: 'monitors' },
  { href: index().url, label: 'notifiers' },
  { href: show().url, label: 'settings' },
] as const;

export function Navigation() {
  const navRef = useRef<HTMLElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);

  const [open, setOpen] = useState(false);

  const toggleMenu = useCallback(() => setOpen((prev) => !prev), []);
  const closeMenu = useCallback(() => setOpen(false), []);

  const handleLogout = useCallback(
    (e: React.MouseEvent) => {
      e.preventDefault();
      router.post(logout().url);
      closeMenu();
    },
    [closeMenu],
  );

  useEffect(() => {
    if (!open) {
      return;
    }

    menuRef.current?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR)?.focus();
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const nav = navRef.current;

    if (!nav) {
      return;
    }

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        closeMenu();
        triggerRef.current?.focus();
        return;
      }

      if (e.key !== 'Tab') {
        return;
      }

      const focusables = Array.from(nav.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).filter(
        (el) => el.offsetParent !== null,
      );

      if (focusables.length === 0) {
        return;
      }

      const first = focusables[0];
      const last = focusables[focusables.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last?.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first?.focus();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [open, closeMenu]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const mediaQuery = window.matchMedia('(min-width: 768px)');
    const handleChange = (e: MediaQueryListEvent) => {
      if (e.matches) {
        closeMenu();
      }
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, [open, closeMenu]);

  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [open]);

  return (
    <>
      {open ? (
        <div
          aria-hidden
          className="fixed inset-0 z-40 bg-background/75 backdrop-blur-xs md:hidden"
          onClick={closeMenu}
        />
      ) : null}

      <header className="fixed inset-x-0 top-0 z-50">
        <nav
          ref={navRef}
          aria-label="Main navigation"
          className="border-b border-border bg-background"
        >
          <div className="mx-auto flex h-12 max-w-6xl items-center gap-4 px-4">
            <Link href={home().url} className="text-accent transition hover:text-foreground">
              <span aria-hidden>[</span>
              <span className="mx-1">openflare</span>
              <span aria-hidden>]</span>
            </Link>

            {NAV_ITEMS.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="hidden text-muted-foreground hover:text-foreground md:block"
              >
                {item.label}
              </Link>
            ))}
            <button
              className="ml-auto hidden text-muted-foreground transition outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-background md:block"
              onClick={handleLogout}
            >
              logout
            </button>

            <Button
              ref={triggerRef}
              variant="tertiary"
              size="icon"
              className="ml-auto md:hidden"
              aria-label={open ? 'Close navigation' : 'Open navigation'}
              aria-expanded={open}
              aria-controls="mobile-menu"
              onClick={toggleMenu}
            >
              {open ? <IconMinus className="size-4" /> : <IconPlus className="size-4" />}
            </Button>
          </div>

          {open ? (
            <div
              ref={menuRef}
              id="mobile-menu"
              role="dialog"
              aria-modal="true"
              aria-label="Mobile navigation"
              className="flex flex-col border-t border-border px-4 py-2 md:hidden"
            >
              {NAV_ITEMS.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className="py-2 text-muted-foreground hover:text-foreground"
                  onClick={closeMenu}
                >
                  {item.label}
                </Link>
              ))}
              <button
                className="py-2 text-left text-muted-foreground transition outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                onClick={handleLogout}
              >
                logout
              </button>
            </div>
          ) : null}
        </nav>
      </header>
    </>
  );
}
