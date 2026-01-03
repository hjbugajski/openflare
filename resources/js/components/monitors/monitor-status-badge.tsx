import { Badge } from '@/components/ui/badge';
import type { MonitorStatus } from '@/types';

interface MonitorStatusBadgeProps {
  status: MonitorStatus | null | undefined;
  isActive: boolean;
  hasIncident: boolean;
}

export function MonitorStatusBadge({ status, isActive, hasIncident }: MonitorStatusBadgeProps) {
  if (!isActive) {
    return <Badge variant="secondary">paused</Badge>;
  }

  if (!status) {
    return <Badge variant="secondary">pending</Badge>;
  }

  if (status === 'up' && !hasIncident) {
    return <Badge variant="success">up</Badge>;
  }

  return <Badge variant="danger">down</Badge>;
}
