import { Card } from '@/components/ui/card';
import { Heading } from '@/components/ui/heading';
import { RadioGroup } from '@/components/ui/radio-group';
import { usePreferencePatch } from '@/lib/hooks/use-preference-patch';
import type { MonitorViewMode } from '@/types';

interface MonitorViewPreferenceProps {
  value: MonitorViewMode;
}

export function MonitorViewPreference({ value }: MonitorViewPreferenceProps) {
  const [currentValue, setCurrentValue] = usePreferencePatch('monitors_view', value);

  return (
    <Card.Root>
      <Card.Header>
        <Heading
          level={2}
          title="Monitor view"
          description="choose your preferred view for the monitors list"
        />
      </Card.Header>
      <Card.Content>
        <RadioGroup.Root
          value={currentValue}
          className="grid-cols-2"
          onValueChange={(v) => setCurrentValue(v as MonitorViewMode)}
        >
          <RadioGroup.Item value="cards" checked={currentValue === 'cards'}>
            cards
          </RadioGroup.Item>
          <RadioGroup.Item value="table" checked={currentValue === 'table'}>
            table
          </RadioGroup.Item>
        </RadioGroup.Root>
      </Card.Content>
    </Card.Root>
  );
}
