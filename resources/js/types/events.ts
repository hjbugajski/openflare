export interface MonitorCheckedEvent {
  monitor_id: string;
  check: {
    id: string;
    status: 'up' | 'down';
    status_code: number;
    response_time_ms: number;
    error_message: string | null;
    checked_at: string;
  };
}

export interface IncidentOpenedEvent {
  monitor_id: string;
  incident: {
    id: string;
    started_at: string;
    cause: string | null;
  };
}

export interface IncidentResolvedEvent {
  monitor_id: string;
  incident: {
    id: string;
    started_at: string;
    ended_at: string | null;
    cause: string | null;
  };
}
