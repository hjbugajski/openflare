import { RadioGroup } from '@/components/ui/radio-group';

export type MonitorMode = 'all' | 'manual';

interface MonitorModeRadioProps {
  value: MonitorMode;
  onValueChange: (value: MonitorMode) => void;
}

export function MonitorModeRadio({ value, onValueChange }: MonitorModeRadioProps) {
  return (
    <RadioGroup.Root
      value={value}
      className="grid-cols-2"
      onValueChange={(v) => onValueChange(v as MonitorMode)}
    >
      <RadioGroup.Item value="all" checked={value === 'all'}>
        apply to all monitors
      </RadioGroup.Item>
      <RadioGroup.Item value="manual" checked={value === 'manual'}>
        manually select monitors
      </RadioGroup.Item>
    </RadioGroup.Root>
  );
}
