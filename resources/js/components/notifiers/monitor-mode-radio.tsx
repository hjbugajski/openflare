import { useCallback } from 'react';

import { RadioGroup } from '@/components/ui/radio-group';

export type MonitorMode = 'all' | 'manual';

interface MonitorModeRadioProps {
  value: MonitorMode;
  onValueChange: (value: MonitorMode) => void;
}

export function MonitorModeRadio({ value, onValueChange }: MonitorModeRadioProps) {
  const handleValueChange = useCallback(
    (v: string) => onValueChange(v as MonitorMode),
    [onValueChange],
  );

  return (
    <RadioGroup.Root value={value} className="grid-cols-2" onValueChange={handleValueChange}>
      <RadioGroup.Item value="all" checked={value === 'all'}>
        apply to all monitors
      </RadioGroup.Item>
      <RadioGroup.Item value="manual" checked={value === 'manual'}>
        manually select monitors
      </RadioGroup.Item>
    </RadioGroup.Root>
  );
}
