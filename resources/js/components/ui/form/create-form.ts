import { createFormHook } from '@tanstack/react-form';

import {
  CheckboxField,
  CheckboxGroupItem,
  ComboboxField,
  Field,
  FieldError,
  NumberInput,
  RadioGroupField,
  SelectField,
  TextAreaInput,
  TextInput,
} from '@/components/ui/form/fields';
import { FormRoot, SubmitButton } from '@/components/ui/form/form-components';
import { fieldContext, formContext } from '@/components/ui/form/form-context';
import { ErrorMessage } from '@/components/ui/label';

export const { useAppForm } = createFormHook({
  fieldContext,
  formContext,
  fieldComponents: {
    Field,
    TextInput,
    NumberInput,
    TextAreaInput,
    SelectField,
    ComboboxField,
    CheckboxField,
    CheckboxGroupItem,
    RadioGroupField,
  },
  formComponents: {
    ErrorMessage,
    FormRoot,
    FieldError,
    SubmitButton,
  },
});
