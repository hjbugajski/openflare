import { useMemo, useRef } from 'react';

import { IconSearchOptions } from '@/components/icons/search-options';
import { Card } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Heading } from '@/components/ui/heading';
import { usePreferencePatch } from '@/lib/hooks/use-preference-patch';

interface TimezoneItem {
  id: string;
  label: string;
}

interface TimezonePreferenceProps {
  value: string;
}

function getTimezoneOptions(currentValue: string): TimezoneItem[] {
  const zones =
    typeof Intl !== 'undefined' && 'supportedValuesOf' in Intl
      ? Intl.supportedValuesOf('timeZone')
      : [];
  const uniqueZones = Array.from(new Set(zones)).sort();

  if (uniqueZones.length === 0) {
    uniqueZones.push('UTC');
  }

  if (!uniqueZones.includes(currentValue)) {
    uniqueZones.unshift(currentValue);
  }

  return uniqueZones.map((zone) => ({ id: zone, label: zone }));
}

export function TimezonePreference({ value }: TimezonePreferenceProps) {
  const [currentValue, setCurrentValue] = usePreferencePatch('timezone', value);
  const timezones = useMemo(() => getTimezoneOptions(currentValue), [currentValue]);
  const containerRef = useRef<HTMLDivElement | null>(null);

  const selectedValue = useMemo(
    () => timezones.find((timezone) => timezone.id === currentValue) ?? timezones[0],
    [currentValue, timezones],
  );

  const handleValueChange = (newValue: TimezoneItem | null) => {
    if (newValue) {
      setCurrentValue(newValue.id);
    }
  };

  return (
    <Card.Root>
      <Card.Header>
        <Heading
          level={2}
          title="Timezone"
          description="set the timezone used for uptime summaries"
        />
      </Card.Header>
      <Card.Content>
        <Combobox.Root items={timezones} value={selectedValue} onValueChange={handleValueChange}>
          <Combobox.Chips ref={containerRef}>
            <Combobox.Value>
              {(selected: TimezoneItem | null) => (
                <Combobox.Input
                  placeholder={selected ? selected.label : 'Select a timezone...'}
                  aria-label="Timezone"
                />
              )}
            </Combobox.Value>
            <span aria-hidden className="ml-auto text-muted-foreground">
              <IconSearchOptions className="size-4" />
            </span>
          </Combobox.Chips>
          <Combobox.Portal>
            <Combobox.Positioner anchor={containerRef} sideOffset={8}>
              <Combobox.Popup>
                <Combobox.Empty>no timezones found.</Combobox.Empty>
                <Combobox.List>
                  {(item: TimezoneItem) => (
                    <Combobox.Item key={item.id} value={item}>
                      <span className="truncate">{item.label}</span>
                    </Combobox.Item>
                  )}
                </Combobox.List>
              </Combobox.Popup>
            </Combobox.Positioner>
          </Combobox.Portal>
        </Combobox.Root>
      </Card.Content>
    </Card.Root>
  );
}
