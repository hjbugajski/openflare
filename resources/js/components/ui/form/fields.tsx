import {
  type ComponentProps,
  createContext,
  useCallback,
  useContext,
  useId,
  useMemo,
  useRef,
} from 'react';

import { type AnyFieldMeta } from '@tanstack/react-form';

import { IconChevronGrabberVertical } from '@/components/icons/chevron-grabber-vertical';
import { IconSearchOptions } from '@/components/icons/search-options';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { useFieldContext } from '@/components/ui/form/form-context';
import { Input } from '@/components/ui/input';
import { Description, ErrorMessage, Label } from '@/components/ui/label';
import { RadioGroup } from '@/components/ui/radio-group';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/cn';

const FieldIdContext = createContext<string | null>(null);

function useFieldId(): string {
  const fieldId = useContext(FieldIdContext);

  if (!fieldId) {
    throw new Error('useFieldId must be used within a Field component');
  }

  return fieldId;
}

interface FieldErrorProps {
  meta: AnyFieldMeta;
  serverError?: string;
}

export function FieldError({ meta, serverError }: FieldErrorProps) {
  if (serverError) {
    return <ErrorMessage>{serverError}</ErrorMessage>;
  }

  if (meta.isValid || !meta.errors.length) {
    return null;
  }

  const error = meta.errors[0];
  const message = typeof error === 'string' ? error : (error as { message?: string })?.message;

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

  return (
    <div {...props} className={cn('grid gap-2', className)}>
      <Label htmlFor={fieldId}>{label}</Label>
      <FieldIdContext.Provider value={fieldId}>{children}</FieldIdContext.Provider>
      {description ? <Description>{description}</Description> : null}
      <FieldError meta={field.state.meta} serverError={serverError} />
    </div>
  );
}

export function TextInput({ type = 'text', ...props }: Omit<ComponentProps<'input'>, 'onChange'>) {
  const field = useFieldContext<string>();
  const fieldId = useFieldId();
  const hasError = !field.state.meta.isValid && field.state.meta.isTouched;

  return (
    <Input
      {...props}
      id={fieldId}
      name={field.name}
      type={type}
      value={field.state.value}
      aria-invalid={hasError || undefined}
      onChange={(e) => field.handleChange(e.target.value)}
      onBlur={field.handleBlur}
    />
  );
}

export function NumberInput(props: Omit<ComponentProps<'input'>, 'type' | 'onChange'>) {
  const field = useFieldContext<number | undefined>();
  const fieldId = useFieldId();
  const hasError = !field.state.meta.isValid && field.state.meta.isTouched;

  return (
    <Input
      {...props}
      id={fieldId}
      name={field.name}
      type="number"
      value={field.state.value ?? ''}
      aria-invalid={hasError || undefined}
      onChange={(e) => {
        const value = e.target.value === '' ? undefined : e.target.valueAsNumber;
        // Only pass valid numbers or undefined; NaN gets converted to undefined
        field.handleChange(Number.isNaN(value) ? undefined : value);
      }}
      onBlur={field.handleBlur}
    />
  );
}

export function TextAreaInput(props: ComponentProps<typeof Textarea>) {
  const field = useFieldContext<string>();
  const fieldId = useFieldId();
  const hasError = !field.state.meta.isValid && field.state.meta.isTouched;

  return (
    <Textarea
      {...props}
      id={fieldId}
      name={field.name}
      value={field.state.value}
      aria-invalid={hasError || undefined}
      onChange={(e) => field.handleChange(e.target.value)}
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
  const fieldId = useFieldId();
  const hasError = !field.state.meta.isValid && field.state.meta.isTouched;

  // Determine if we should preserve numeric type based on current field value
  const isNumeric = typeof field.state.value === 'number';

  const handleValueChange = (value: unknown) => {
    // Base UI may return string even for numeric values; coerce if needed
    if (isNumeric && typeof value === 'string') {
      const parsed = Number(value);
      field.handleChange(Number.isNaN(parsed) ? value : parsed);
    } else {
      field.handleChange(value as string | number);
    }
  };

  return (
    <Select.Root value={field.state.value} disabled={disabled} onValueChange={handleValueChange}>
      <Select.Trigger
        id={fieldId}
        disabled={disabled}
        aria-invalid={hasError || undefined}
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
  const fieldId = useFieldId();
  const hasError = !field.state.meta.isValid && field.state.meta.isTouched;
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
      <Combobox.Chips ref={containerRef} className={cn(hasError && 'border-destructive')}>
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
                id={fieldId}
                placeholder={selectedValue.length > 0 ? '' : placeholder}
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

  return (
    <div className="flex items-start gap-2">
      <Checkbox.Root
        id={fieldId}
        checked={field.state.value}
        disabled={disabled}
        className="mt-0.5"
        onCheckedChange={(checked) => field.handleChange(!!checked)}
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

  const handleChange = (checked: boolean) => {
    const current = field.state.value;
    if (checked) {
      field.handleChange([...current, id]);
    } else {
      field.handleChange(current.filter((v: string) => v !== id));
    }
  };

  return (
    <div className="flex items-center gap-2">
      <Checkbox.Root
        id={fieldId}
        checked={isChecked}
        disabled={disabled}
        onCheckedChange={(checked) => handleChange(!!checked)}
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
      onValueChange={(value) => field.handleChange(value as string)}
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
