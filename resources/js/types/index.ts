export type MonitorViewMode = 'cards' | 'table';

export interface UserPreferences {
  monitors_view?: MonitorViewMode;
  timezone?: string;
  timezone_rollups_timezone?: string;
  timezone_rollups_ran_at?: string;
}

export interface User {
  id: number;
  uuid: string;
  name: string;
  email: string;
  email_verified_at: string | null;
  preferences: UserPreferences | null;
}

export type NotifierType = 'discord' | 'email';

export type HttpMethod = 'GET' | 'HEAD';

export type MonitorStatus = 'up' | 'down';

export type StatusToolbarState = 'operational' | 'degraded' | 'incident';

export interface StatusToolbarSummary {
  state: StatusToolbarState;
  totalMonitors: number;
  activeMonitors: number;
  activeIncidentCount: number;
  recentFailureCount: number;
}

export interface Monitor {
  id: string;
  name: string;
  url: string;
  method: HttpMethod;
  interval: number;
  timeout: number;
  is_active: boolean;
  expected_status_code: number;
  last_checked_at: string | null;
  latest_check?: MonitorCheck | null;
  current_incident?: Incident | null;
  checks_count?: number;
  notifiers?: NotifierSummary[];
  daily_rollups?: DailyUptimeRollup[];
}

export interface MonitorCheck {
  id: string;
  monitor_id: string;
  status: MonitorStatus;
  response_time_ms: number;
  status_code: number;
  error_message: string | null;
  checked_at: string;
}

export interface Incident {
  id: string;
  monitor_id: string;
  started_at: string;
  ended_at: string | null;
  cause: string | null;
}

export interface IncidentWithMonitor extends Incident {
  monitor: MonitorSummary;
}

/** Summary type for notifier when used in lists/associations */
export interface NotifierSummary {
  id: string;
  name: string;
  type: NotifierType;
  is_active: boolean;
  is_default?: boolean;
  apply_to_all?: boolean;
  pivot?: {
    is_excluded: boolean;
  };
}

/** Summary type for monitor when used in lists/associations */
export interface MonitorSummary {
  id: string;
  name: string;
  url: string;
  pivot?: {
    is_excluded: boolean;
  };
}

/** Full notifier with config details */
export interface Notifier {
  id: string;
  name: string;
  type: NotifierType;
  config: {
    webhook_url?: string;
    email?: string;
  };
  is_active: boolean;
  is_default: boolean;
  apply_to_all: boolean;
  monitors_count?: number;
  excluded_monitors_count?: number;
  monitors?: MonitorSummary[];
  created_at?: string;
  updated_at?: string;
}

export interface DailyUptimeRollup {
  id: string;
  monitor_id: string;
  date: string;
  total_checks: number;
  successful_checks: number;
  uptime_percentage: number;
  avg_response_time_ms: number | null;
  min_response_time_ms: number | null;
  max_response_time_ms: number | null;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: {
    url: string | null;
    label: string;
    active: boolean;
  }[];
  first_page_url: string;
  last_page_url: string;
  next_page_url: string | null;
  prev_page_url: string | null;
  path: string;
}

export interface ReverbConfig {
  key: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User | null;
  };
  reverb: ReverbConfig;
  statusToolbar?: StatusToolbarSummary | null;
};
