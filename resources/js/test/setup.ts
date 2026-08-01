import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

import '@testing-library/jest-dom/vitest';

afterEach(cleanup);

const IMPLICIT_SUBMISSION_INPUT_TYPES = new Set([
  'date',
  'datetime-local',
  'email',
  'month',
  'number',
  'password',
  'search',
  'tel',
  'text',
  'time',
  'url',
  'week',
]);

const SUBMIT_BUTTON_SELECTOR = 'button:not([type]), button[type="submit"], input[type="submit"]';

/*
 * happy-dom does not implement implicit submission, so pressing Enter in a text
 * field never reaches the form. Mirror the HTML spec: click the form's default
 * button, or submit directly when the form has a single text field and no
 * button at all.
 */
document.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter' || event.defaultPrevented) {
    return;
  }

  const field = event.target;

  if (
    !(field instanceof HTMLInputElement) ||
    !IMPLICIT_SUBMISSION_INPUT_TYPES.has(field.type) ||
    !field.form
  ) {
    return;
  }

  const form = field.form;
  const defaultButton = Array.from(
    document.querySelectorAll<HTMLButtonElement | HTMLInputElement>(SUBMIT_BUTTON_SELECTOR),
  ).find((button) => button.form === form);

  if (defaultButton) {
    if (!defaultButton.disabled) {
      defaultButton.click();
    }

    return;
  }

  const blockingFields = Array.from(form.elements).filter(
    (element) =>
      element instanceof HTMLInputElement && IMPLICIT_SUBMISSION_INPUT_TYPES.has(element.type),
  );

  if (blockingFields.length === 1) {
    form.requestSubmit();
  }
});
