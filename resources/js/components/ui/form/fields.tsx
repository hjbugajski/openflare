import {
  type ChangeEvent,
  type ComponentProps,
  createContext,
  useCallback,
  useContext,
  useId,
  useMemo,
  useRef,
} from 'react';

import { type AnyFieldMeta } from '@tanstack/react-form';
import { IconChevronGrabberVertical } from 'central-icons/IconChevronGrabberVertical';
import { IconSearchOptions } from 'central-icons/IconSearchOptions';

import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { useFieldContext } from '@/components/ui/form/form-context';
import { Input } from '@/components/ui/input';
import { Description, ErrorMessage, Label } from '@/components/ui/label';
import { RadioGroup } from '@/components/ui/radio-group';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/cn';

/** Everything `Field` works out about its control and hands down to it. */
interface FieldWiring {
  id: string;
  /**
   * Null whenever the matching element is not rendered. `aria-describedby` must
   * only ever point at ids that exist in the DOM, so `Field` — the one component
   * that knows what it rendered — nulls these out rather than making every input
   * guess.
   */
  descriptionId: string | null;
  errorId: string | null;
  /**
   * Server errors arrive as a prop on `Field` alone, so the controls cannot see
   * them; only `Field` can decide invalidity for both error sources. Client-side
   * errors stay gated on `isTouched` so a pristine form does not open red.
   */
  invalid: boolean;
}

const FieldWiringContext = createContext<FieldWiring | null>(null);

function useFieldWiring(): FieldWiring {
  const wiring = useContext(FieldWiringContext);

  if (!wiring) {
    throw new Error('useFieldWiring must be used within a Field component');
  }

  return wiring;
}

function describedBy({ descriptionId, errorId }: FieldWiring): string | undefined {
  return [descriptionId, errorId].filter(Boolean).join(' ') || undefined;
}

function errorMessageOf(meta: AnyFieldMeta, serverError?: string): string | undefined {
  if (serverError) {
    return serverError;
  }

  if (meta.isValid || !meta.errors.length) {
    return undefined;
  }

  const error = meta.errors[0];

  return typeof error === 'string' ? error : (error as { message?: string }).message;
}

interface FieldErrorProps {
  meta: AnyFieldMeta;
  serverError?: string;
}

export function FieldError({ meta, serverError }: FieldErrorProps) {
  const message = errorMessageOf(meta, serverError);

  return message ? <ErrorMessage>{message}</ErrorMessage> : null;
}

interface FieldProps extends ComponentProps<'div'> {
  label: string;
  description?: string;
  serverError?: string;
}

export function Field({
  className,
  children,
  label,
  description,
  serverError,
  ...props
}: FieldProps) {
  const field = useFieldContext<string>();
  const uniqueId = useId();
  const fieldId = `${field.name}-${uniqueId}`;
  const errorMessage = errorMessageOf(field.state.meta, serverError);
  const { isValid, isTouched } = field.state.meta;

  const wiring = useMemo(
    () => ({
      id: fieldId,
      descriptionId: description ? `${fieldId}-description` : null,
      errorId: errorMessage ? `${fieldId}-error` : null,
      invalid: Boolean(serverError) || (!isValid && isTouched),
    }),
    [fieldId, description, errorMessage, serverError, isValid, isTouched],
  );

  return (
    <div {...props} className={cn('grid gap-2', className)}>
      <Label htmlFor={fieldId}>{label}</Label>
      <FieldWiringContext.Provider value={wiring}>{children}</FieldWiringContext.Provider>
      {wiring.descriptionId ? (
        <Description id={wiring.descriptionId}>{description}</Description>
      ) : null}
      {wiring.errorId ? <ErrorMessage id={wiring.errorId}>{errorMessage}</ErrorMessage> : null}
    </div>
  );
}

export function TextInput({ type = 'text', ...props }: Omit<ComponentProps<'input'>, 'onChange'>) {
  const field = useFieldContext<string>();
  const wiring = useFieldWiring();

  const handleChange = useCallback(
    (e: ChangeEvent<HTMLInputElement>) => field.handleChange(e.target.value),
    [field],
  );

  return (
    <Input
      {...props}
      id={wiring.id}
      name={field.name}
      type={type}
      value={field.state.value}
      aria-describedby={describedBy(wiring)}
      aria-invalid={wiring.invalid || undefined}
      onChange={handleChange}
      onBlur={field.handleBlur}
    />
  );
}

export function NumberInput(props: Omit<ComponentProps<'input'>, 'type' | 'onChange'>) {
  const field = useFieldContext<number | undefined>();
  const wiring = useFieldWiring();

  const handleChange = useCallback(
    (e: ChangeEvent<HTMLInputElement>) => {
      const value = e.target.value === '' ? undefined : e.target.valueAsNumber;
      field.handleChange(Number.isNaN(value) ? undefined : value);
    },
    [field],
  );

  return (
    <Input
      {...props}
      id={wiring.id}
      name={field.name}
      type="number"
      value={field.state.value ?? ''}
      aria-describedby={describedBy(wiring)}
      aria-invalid={wiring.invalid || undefined}
      onChange={handleChange}
      onBlur={field.handleBlur}
    />
  );
}

export function TextAreaInput(props: ComponentProps<typeof Textarea>) {
  const field = useFieldContext<string>();
  const wiring = useFieldWiring();

  const handleChange = useCallback(
    (e: ChangeEvent<HTMLTextAreaElement>) => field.handleChange(e.target.value),
    [field],
  );

  return (
    <Textarea
      {...props}
      id={wiring.id}
      name={field.name}
      value={field.state.value}
      aria-describedby={describedBy(wiring)}
      aria-invalid={wiring.invalid || undefined}
      onChange={handleChange}
      onBlur={field.handleBlur}
    />
  );
}

interface SelectItem {
  value: string | number;
  label: string;
}

interface SelectFieldProps {
  items: SelectItem[];
  disabled?: boolean;
}

export function SelectField({ items, disabled }: SelectFieldProps) {
  const field = useFieldContext<string | number>();
  const wiring = useFieldWiring();

  const isNumeric = typeof field.state.value === 'number';

  const handleValueChange = useCallback(
    (value: unknown) => {
      // Base UI may return a string even for numeric values
      if (isNumeric && typeof value === 'string') {
        const parsed = Number(value);
        field.handleChange(Number.isNaN(parsed) ? value : parsed);
      } else {
        field.handleChange(value as string | number);
      }
    },
    [field, isNumeric],
  );

  return (
    <Select.Root value={field.state.value} disabled={disabled} onValueChange={handleValueChange}>
      <Select.Trigger
        id={wiring.id}
        disabled={disabled}
        aria-describedby={describedBy(wiring)}
        aria-invalid={wiring.invalid || undefined}
        onBlur={field.handleBlur}
      >
        <Select.Value>
          {items.find((item) => item.value === field.state.value)?.label ?? ''}
        </Select.Value>
        <Select.Icon>
          <IconChevronGrabberVertical className="size-4" />
        </Select.Icon>
      </Select.Trigger>
      <Select.Portal>
        <Select.Positioner>
          <Select.Popup>
            {items.map(({ value, label }) => (
              <Select.Item key={value} value={value}>
                <Select.ItemText>{label}</Select.ItemText>
              </Select.Item>
            ))}
          </Select.Popup>
        </Select.Positioner>
      </Select.Portal>
    </Select.Root>
  );
}

export interface ComboboxItem {
  id: string;
  label: string;
  description?: string;
}

interface ComboboxFieldProps<T extends ComboboxItem> {
  items: T[];
  placeholder?: string;
  emptyMessage?: string;
  disabled?: boolean;
}

export function ComboboxField<T extends ComboboxItem>({
  items,
  placeholder = 'select...',
  emptyMessage = 'no items found.',
  disabled,
}: ComboboxFieldProps<T>) {
  const field = useFieldContext<string[]>();
  const wiring = useFieldWiring();
  const containerRef = useRef<HTMLDivElement | null>(null);

  const selectedItems = useMemo(
    () => items.filter((item) => field.state.value.includes(item.id)),
    [items, field.state.value],
  );

  const handleValueChange = useCallback(
    (newValue: T[] | null) => {
      field.handleChange(newValue?.map((item) => item.id) ?? []);
    },
    [field],
  );

  return (
    <Combobox.Root<T, true>
      multiple
      disabled={disabled}
      items={items}
      value={selectedItems}
      onValueChange={handleValueChange}
    >
      <Combobox.Chips ref={containerRef}>
        <Combobox.Value>
          {(selectedValue: T[]) => (
            <>
              {selectedValue.map((item) => (
                <Combobox.Chip key={item.id} aria-label={item.label}>
                  <span className="truncate">{item.label}</span>
                  <Combobox.ChipRemove aria-label="remove">
                    <XIcon className="size-3 shrink-0" />
                  </Combobox.ChipRemove>
                </Combobox.Chip>
              ))}
              <Combobox.Input
                id={wiring.id}
                placeholder={selectedValue.length > 0 ? '' : placeholder}
                aria-describedby={describedBy(wiring)}
                aria-invalid={wiring.invalid || undefined}
                onBlur={field.handleBlur}
              />
            </>
          )}
        </Combobox.Value>
        <span aria-hidden className="ml-auto text-muted-foreground">
          <IconSearchOptions className="size-4" />
        </span>
      </Combobox.Chips>

      <Combobox.Portal>
        <Combobox.Positioner anchor={containerRef} sideOffset={8}>
          <Combobox.Popup>
            <Combobox.Empty>{emptyMessage}</Combobox.Empty>
            <Combobox.List>
              {(item: T) => (
                <Combobox.Item key={item.id} value={item}>
                  <span className="truncate">{item.label}</span>
                </Combobox.Item>
              )}
            </Combobox.List>
          </Combobox.Popup>
        </Combobox.Positioner>
      </Combobox.Portal>
    </Combobox.Root>
  );
}

function XIcon(props: ComponentProps<'svg'>) {
  return (
    <svg
      aria-hidden
      fill="none"
      height={16}
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      viewBox="0 0 24 24"
      width={16}
      xmlns="http://www.w3.org/2000/svg"
      {...props}
    >
      <path d="M18 6 6 18" />
      <path d="m6 6 12 12" />
    </svg>
  );
}

interface CheckboxFieldProps {
  label: string;
  description?: string;
  disabled?: boolean;
}

export function CheckboxField({ label, description, disabled }: CheckboxFieldProps) {
  const field = useFieldContext<boolean>();
  const uniqueId = useId();
  const fieldId = `${field.name}-${uniqueId}`;

  const handleCheckedChange = useCallback(
    (checked: boolean) => field.handleChange(!!checked),
    [field],
  );

  return (
    <div className="flex items-start gap-2">
      <Checkbox.Root
        id={fieldId}
        checked={field.state.value}
        disabled={disabled}
        className="mt-0.5"
        onCheckedChange={handleCheckedChange}
        onBlur={field.handleBlur}
      >
        <Checkbox.Indicator />
      </Checkbox.Root>
      <div className="flex flex-col gap-1">
        <label htmlFor={fieldId} className="text-sm font-normal">
          {label}
        </label>
        {description ? <Description>{description}</Description> : null}
      </div>
    </div>
  );
}

interface CheckboxGroupItemProps {
  id: string;
  label: string;
  description?: string;
  disabled?: boolean;
}

export function CheckboxGroupItem({ id, label, description, disabled }: CheckboxGroupItemProps) {
  const field = useFieldContext<string[]>();
  const uniqueId = useId();
  const fieldId = `${field.name}-${id}-${uniqueId}`;
  const isChecked = field.state.value.includes(id);

  const handleChange = useCallback(
    (checked: boolean) => {
      const current = field.state.value;
      if (checked) {
        field.handleChange([...current, id]);
      } else {
        field.handleChange(current.filter((v: string) => v !== id));
      }
    },
    [field, id],
  );

  const handleCheckedChange = useCallback(
    (checked: boolean) => handleChange(!!checked),
    [handleChange],
  );

  return (
    <div className="flex items-center gap-2">
      <Checkbox.Root
        id={fieldId}
        checked={isChecked}
        disabled={disabled}
        onCheckedChange={handleCheckedChange}
        onBlur={field.handleBlur}
      >
        <Checkbox.Indicator />
      </Checkbox.Root>
      <label htmlFor={fieldId} className="text-sm font-normal">
        {label}
        {description ? <span className="text-muted-foreground"> ({description})</span> : null}
      </label>
    </div>
  );
}

interface RadioGroupItem {
  value: string;
  label: string;
}

interface RadioGroupFieldProps {
  items: RadioGroupItem[];
  columns?: number;
  disabled?: boolean;
}

export function RadioGroupField({ items, columns = 2, disabled }: RadioGroupFieldProps) {
  const field = useFieldContext<string>();

  return (
    <RadioGroup.Root
      value={field.state.value}
      disabled={disabled}
      className={cn(columns === 2 && 'grid-cols-2')}
      onValueChange={field.handleChange}
    >
      {items.map((item) => (
        <RadioGroup.Item
          key={item.value}
          value={item.value}
          checked={field.state.value === item.value}
          disabled={disabled}
        >
          {item.label}
        </RadioGroup.Item>
      ))}
    </RadioGroup.Root>
  );
}
