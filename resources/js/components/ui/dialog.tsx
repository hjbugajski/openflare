import { Dialog as BaseDialog, type DialogRootProps } from '@base-ui/react/dialog';
import { type VariantProps, cva } from 'class-variance-authority';
import { type ComponentProps, createContext, useContext } from 'react';

import { IconClose } from '@/components/icons/close';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/cn';

interface DialogContextValue {
  mode: 'dialog' | 'side' | 'bottom' | 'full';
}

const DialogContext = createContext<DialogContextValue | null>(null);

const useDialogContext = () => {
  const context = useContext(DialogContext);

  if (!context) {
    throw new Error('Dialog components must be used within Dialog.Root');
  }

  return context;
};

function Root<Payload = unknown>(props: DialogRootProps<Payload>) {
  return <BaseDialog.Root {...props} />;
}

function Trigger(props: ComponentProps<typeof BaseDialog.Trigger>) {
  return <BaseDialog.Trigger {...props} />;
}

function Portal(props: ComponentProps<typeof BaseDialog.Portal>) {
  return <BaseDialog.Portal {...props} />;
}

function Backdrop({ className, ...props }: ComponentProps<typeof BaseDialog.Backdrop>) {
  return (
    <BaseDialog.Backdrop
      className={cn(
        'fixed inset-0 bg-background/75 backdrop-blur-xs transition-opacity duration-200',
        'data-starting-style:opacity-0',
        'data-ending-style:opacity-0',
        className,
      )}
      {...props}
    />
  );
}

const contentVariants = cva(
  [
    'fixed z-50 flex flex-col overflow-scroll border-t border-t-accent bg-background-secondary outline-none',
    'transition-all duration-200 ease-in-out',
    'focus-visible:ring focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-background',
  ],
  {
    variants: {
      mode: {
        dialog: cn(
          'top-1/2 left-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2',
          'data-starting-style:scale-95 data-starting-style:opacity-0',
          'data-ending-style:scale-95 data-ending-style:opacity-0',
          'max-md:inset-x-0 max-md:top-auto max-md:bottom-0 max-md:max-h-[80vh] max-md:max-w-none max-md:translate-x-0 max-md:translate-y-0',
          'max-md:data-starting-style:translate-y-full max-md:data-starting-style:scale-100 max-md:data-starting-style:opacity-100',
          'max-md:data-ending-style:translate-y-full max-md:data-ending-style:scale-100 max-md:data-ending-style:opacity-100',
        ),
        side: cn(
          'inset-y-2 right-2 w-3/4 sm:max-w-md',
          'data-starting-style:translate-x-full',
          'data-ending-style:translate-x-full',
          'max-md:inset-x-0 max-md:top-auto max-md:right-0 max-md:bottom-0 max-md:max-h-[80vh] max-md:w-full',
          'max-md:data-starting-style:translate-x-0 max-md:data-starting-style:translate-y-full',
          'max-md:data-ending-style:translate-x-0 max-md:data-ending-style:translate-y-full',
        ),
        bottom: cn(
          'inset-x-0 top-auto bottom-0 max-h-[80vh]',
          'data-starting-style:translate-y-full',
          'data-ending-style:translate-y-full',
        ),
        full: cn(
          'inset-4 w-auto',
          'data-starting-style:translate-x-full',
          'data-ending-style:translate-x-full',
        ),
      },
    },
    defaultVariants: {
      mode: 'dialog',
    },
  },
);

type DialogContentProps = ComponentProps<typeof BaseDialog.Popup> &
  VariantProps<typeof contentVariants>;

function Content({ className, children, mode = 'dialog', ...props }: DialogContentProps) {
  return (
    <BaseDialog.Popup
      data-mode={mode}
      className={cn(contentVariants({ mode }), className)}
      {...props}
    >
      <DialogContext.Provider value={{ mode: mode || 'dialog' }}>{children}</DialogContext.Provider>
    </BaseDialog.Popup>
  );
}

interface DialogHeaderProps extends ComponentProps<'div'> {
  closeButton?: boolean;
}

function Header({ className, children, closeButton, ...props }: DialogHeaderProps) {
  return (
    <div
      className={cn(
        'sticky top-0 z-30 flex flex-col gap-2 bg-background-secondary p-4',
        { 'pr-12': closeButton },
        className,
      )}
      {...props}
    >
      {children}
      {closeButton ? (
        <Close
          render={<Button variant="tertiary" size="icon" className="absolute top-3 right-3" />}
        >
          <span className="sr-only">Close</span>
          <IconClose className="h-4 w-4" />
        </Close>
      ) : null}
    </div>
  );
}

interface DialogTitleProps extends ComponentProps<typeof BaseDialog.Title> {
  level?: 1 | 2 | 3 | 4 | 5 | 6;
}

function Title({ className, level = 2, children, ...props }: DialogTitleProps) {
  const hashes = '#'.repeat(level);

  return (
    <BaseDialog.Title className={cn('text-lg font-semibold uppercase', className)} {...props}>
      <span aria-hidden className="text-accent">
        {hashes}
      </span>{' '}
      {children}
    </BaseDialog.Title>
  );
}

function Description({ className, ...props }: ComponentProps<typeof BaseDialog.Description>) {
  return (
    <BaseDialog.Description
      className={cn('text-sm text-pretty text-muted-foreground', className)}
      {...props}
    />
  );
}

function Body({ className, ...props }: ComponentProps<'div'>) {
  return <div className={cn('flex flex-col gap-4 px-4', className)} {...props} />;
}

function Footer({ className, ...props }: ComponentProps<'div'>) {
  const { mode } = useDialogContext();

  return (
    <div
      data-mode={mode}
      className={cn(
        'flex gap-2 bg-background-secondary p-4',
        'data-[mode=dialog]:flex-col-reverse data-[mode=dialog]:sm:flex-row data-[mode=dialog]:sm:justify-end',
        'data-[mode=dialog]:max-md:sticky data-[mode=dialog]:max-md:bottom-0 data-[mode=dialog]:max-md:w-full data-[mode=dialog]:max-md:[&>button]:w-full',
        'data-[mode=side]:sticky data-[mode=side]:bottom-0 data-[mode=side]:mt-auto data-[mode=side]:w-full data-[mode=side]:flex-col',
        'data-[mode=bottom]:sticky data-[mode=bottom]:bottom-0 data-[mode=bottom]:w-full data-[mode=bottom]:flex-col-reverse data-[mode=bottom]:[&>button]:w-full',
        'data-[mode=full]:sticky data-[mode=full]:bottom-0 data-[mode=full]:mt-auto data-[mode=full]:w-full data-[mode=full]:flex-row data-[mode=full]:justify-end',
        className,
      )}
      {...props}
    />
  );
}

function Close({ className, ...props }: ComponentProps<typeof BaseDialog.Close>) {
  return <BaseDialog.Close className={cn('', className)} {...props} />;
}

export const Dialog = {
  Root,
  Trigger,
  Portal,
  Backdrop,
  Content,
  Header,
  Title,
  Description,
  Body,
  Footer,
  Close,
  createHandle: BaseDialog.createHandle,
};
