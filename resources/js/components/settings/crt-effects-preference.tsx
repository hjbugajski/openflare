import { useCallback } from 'react';

import { Card } from '@/components/ui/card';
import { Heading } from '@/components/ui/heading';
import { RadioGroup } from '@/components/ui/radio-group';
import { usePreferencePatch } from '@/lib/hooks/use-preference-patch';

interface CrtEffectsPreferenceProps {
  value: boolean;
}

export function CrtEffectsPreference({ value }: CrtEffectsPreferenceProps) {
  const [currentValue, setCurrentValue] = usePreferencePatch('crt_effects', value);

  const handleValueChange = useCallback(
    (v: string) => {
      const enabled = v === 'on';
      setCurrentValue(enabled);
      document.documentElement.classList.toggle('crt-effects', enabled);
    },
    [setCurrentValue],
  );

  return (
    <Card.Root>
      <Card.Header>
        <Heading
          level={2}
          title="CRT effects"
          description="enable retro CRT monitor visual effects"
        />
      </Card.Header>
      <Card.Content>
        <RadioGroup.Root
          value={currentValue ? 'on' : 'off'}
          className="grid-cols-2"
          onValueChange={handleValueChange}
        >
          <RadioGroup.Item value="on" checked={currentValue}>
            on
          </RadioGroup.Item>
          <RadioGroup.Item value="off" checked={!currentValue}>
            off
          </RadioGroup.Item>
        </RadioGroup.Root>
      </Card.Content>
    </Card.Root>
  );
}
