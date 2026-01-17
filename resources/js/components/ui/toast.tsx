import { Toast as BaseToast } from '@base-ui/react/toast';
import { type VariantProps, cva } from 'class-variance-authority';
import { type ComponentProps, type ReactNode, createContext, useContext } from 'react';

import { IconClose } from '@/components/icons/close';
import { cn } from '@/lib/cn';

type ToastVariant = 'default' | 'accent' | 'info' | 'warning' | 'success' | 'destructive';

interface ToastData {
  variant?: ToastVariant;
}

interface ToastContextValue {
  variant: ToastVariant;
}

const ToastContext = createContext<ToastContextValue | null>(null);

const useToastContext = () => {
  const context = useContext(ToastContext);

  if (!context) {
    throw new Error('Toast components must be used within Toast.Root');
  }

  return context;
};

const toastManager = BaseToast.createToastManager();

function Provider({ children }: { children: ReactNode }) {
  return (
    <BaseToast.Provider toastManager={toastManager} timeout={5000}>
      {children}
      <Portal>
        <Viewport>
          <ToastList />
        </Viewport>
      </Portal>
    </BaseToast.Provider>
  );
}

function ToastList() {
  const { toasts } = BaseToast.useToastManager();

  return toasts.map((toast) => {
    const data = toast.data as ToastData | undefined;

    return (
      <Root key={toast.id} toast={toast} variant={data?.variant}>
        <Content>
          {toast.title && <Title>{toast.title}</Title>}
          {toast.description && <Description>{toast.description}</Description>}
          <Close aria-label="close">
            <IconClose className="size-4" />
          </Close>
        </Content>
      </Root>
    );
  });
}

function Portal(props: ComponentProps<typeof BaseToast.Portal>) {
  return <BaseToast.Portal {...props} />;
}

function Viewport({ className, ...props }: ComponentProps<typeof BaseToast.Viewport>) {
  return (
    <BaseToast.Viewport
      className={cn(
        'fixed right-4 bottom-4 z-50 flex w-full max-w-sm flex-col items-end outline-none',
        className,
      )}
      {...props}
    />
  );
}

const rootVariants = cva(
  [
    'pointer-events-auto absolute right-0 bottom-0 w-full border-t transition-all duration-200',
    // z-index stacking: 0th toast at front
    '[z-calc(100-var(--toast-index))]',
    // scale down toasts behind front
    'scale-[calc(1-0.05*var(--toast-index))]',
    // translate up slightly when stacked (collapsed)
    'translate-y-[calc(var(--toast-index)*-0.5rem)]',
    // when expanded, use negative offset-y to expand upward
    'data-expanded:translate-y-[calc(var(--toast-offset-y)*-1)]',
    'data-expanded:scale-100',
    // entry animation
    'data-starting-style:translate-x-[calc(100%+1rem)] data-starting-style:opacity-0',
    // exit animation
    'data-ending-style:translate-x-[calc(100%+1rem)] data-ending-style:opacity-0',
  ],
  {
    variants: {
      variant: {
        default: 'border-t-muted-foreground bg-background-secondary',
        accent: 'border-t-accent bg-background-secondary',
        info: 'border-t-info bg-background-secondary',
        warning: 'border-t-warning bg-background-secondary',
        success: 'border-t-success bg-background-secondary',
        destructive: 'border-t-danger bg-background-secondary',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
);

interface RootProps
  extends Omit<ComponentProps<typeof BaseToast.Root>, 'toast'>, VariantProps<typeof rootVariants> {
  toast: ComponentProps<typeof BaseToast.Root>['toast'];
}

function Root({ className, variant = 'default', toast, children, ...props }: RootProps) {
  return (
    <BaseToast.Root
      toast={toast}
      className={cn(rootVariants({ variant }), className)}
      swipeDirection={['right', 'down']}
      {...props}
    >
      <ToastContext.Provider value={{ variant: variant || 'default' }}>
        {children}
      </ToastContext.Provider>
    </BaseToast.Root>
  );
}

function Content({ className, ...props }: ComponentProps<typeof BaseToast.Content>) {
  return (
    <BaseToast.Content
      className={cn(
        'flex w-full flex-col gap-1 overflow-hidden p-4 pr-10 transition-opacity duration-200',
        // hide content and disable interaction on toasts behind front when collapsed
        'data-behind:pointer-events-none data-behind:opacity-0',
        'data-expanded:pointer-events-auto data-expanded:opacity-100',
        className,
      )}
      {...props}
    />
  );
}

const titleVariants = cva('text-sm font-semibold', {
  variants: {
    variant: {
      default: 'text-foreground',
      accent: 'text-accent',
      info: 'text-info',
      warning: 'text-warning',
      success: 'text-success',
      destructive: 'text-danger',
    },
  },
  defaultVariants: {
    variant: 'default',
  },
});

function Title({ className, ...props }: ComponentProps<typeof BaseToast.Title>) {
  const { variant } = useToastContext();

  return (
    <BaseToast.Title
      render={<span />}
      className={cn(titleVariants({ variant }), className)}
      {...props}
    />
  );
}

function Description({ className, ...props }: ComponentProps<typeof BaseToast.Description>) {
  return (
    <BaseToast.Description className={cn('text-sm text-muted-foreground', className)} {...props} />
  );
}

function Action({ className, ...props }: ComponentProps<typeof BaseToast.Action>) {
  return <BaseToast.Action className={cn('', className)} {...props} />;
}

function Close({ className, ...props }: ComponentProps<typeof BaseToast.Close>) {
  return (
    <BaseToast.Close
      className={cn(
        'absolute top-4 right-4 text-muted-foreground transition hover:text-foreground',
        className,
      )}
      {...props}
    />
  );
}

type ToastOptions = {
  title?: ReactNode;
  description?: ReactNode;
  variant?: ToastVariant;
  timeout?: number;
};

function toast({ title, description, variant, timeout }: ToastOptions) {
  return toastManager.add({
    title,
    description,
    timeout,
    data: { variant },
  });
}

toast.default = (options: Omit<ToastOptions, 'variant'>) =>
  toast({ ...options, variant: 'default' });
toast.accent = (options: Omit<ToastOptions, 'variant'>) => toast({ ...options, variant: 'accent' });
toast.info = (options: Omit<ToastOptions, 'variant'>) => toast({ ...options, variant: 'info' });
toast.warning = (options: Omit<ToastOptions, 'variant'>) =>
  toast({ ...options, variant: 'warning' });
toast.success = (options: Omit<ToastOptions, 'variant'>) =>
  toast({ ...options, variant: 'success' });
toast.destructive = (options: Omit<ToastOptions, 'variant'>) =>
  toast({ ...options, variant: 'destructive' });

export const Toast = {
  Provider,
  Portal,
  Viewport,
  Root,
  Content,
  Title,
  Description,
  Action,
  Close,
  useToastManager: () => BaseToast.useToastManager(),
};

export { toast };
