export type ServiceTypeValue =
    | 'http'
    | 'tcp'
    | 'ssh'
    | 'ping'
    | 'database'
    | 'dns'
    | 'smtp'
    | 'influx_daemon';

export type ServiceImportance = 'low' | 'normal' | 'high' | 'critical';

export type ServiceOwner = {
    id: number;
    name: string;
    email: string;
};

export type Service = {
    id: number;
    name: string;
    description: string | null;
    location: string | null;
    type: ServiceTypeValue;
    type_label: string;
    host: string;
    port: number | null;
    use_ssl: boolean;
    importance: ServiceImportance;
    importance_label: string;
    check_interval: number;
    timeout: number;
    collect_metrics: boolean;
    stream_metrics: boolean;
    enabled: boolean;
    user_id: number | null;
    owner?: ServiceOwner | null;
    created_at: string;
    updated_at: string;
};

export type ServiceTypeOption = {
    value: ServiceTypeValue;
    label: string;
    uses_port: boolean;
    default_port: number | null;
    default_ssl_port: number | null;
};

export type ServiceOptions = {
    types: ServiceTypeOption[];
    importances: { value: ServiceImportance; label: string }[];
};

export type ServiceFilters = {
    search?: string;
    type?: ServiceTypeValue;
    importance?: ServiceImportance;
    enabled?: 'true' | 'false';
    owner?: string;
};

/**
 * Where a service stands with the background collector.
 *
 * - `pending`: collecting, but not checked yet
 * - `paused`: monitoring or background collection is switched off
 * - `unsupported`: the type is not checked by the background collector
 */
export type ServiceState = 'up' | 'down' | 'pending' | 'paused' | 'unsupported';

export type ServiceCheck = {
    id: number;
    checked_at: string;
    successful: boolean;
    latency_ms: number | null;
    status_code: number | null;
    error: string | null;
};

export type ServiceStatus = {
    state: ServiceState;
    /** When the service last went down or came back up. */
    since: string | null;
    last_check: ServiceCheck | null;
    next_check_at: string | null;
};

export type TimeRangeValue = '24h' | '7d' | '30d';

export type TimeRangeOption = {
    value: TimeRangeValue;
    label: string;
};

export type ServiceSeriesPoint = {
    time: string;
    checks: number;
    successful: number;
    failed: number;
    average_latency_ms: number | null;
    max_latency_ms: number | null;
};

export type ServiceOverview = {
    stats: {
        uptime: number | null;
        checks: number;
        failed_checks: number;
        average_latency_ms: number | null;
        p95_latency_ms: number | null;
        incidents: number;
    };
    series: ServiceSeriesPoint[];
    /** Null for services that are not HTTP. */
    status_codes: { status_code: number; count: number }[] | null;
    recent_checks: ServiceCheck[];
};

export type ServiceDailyUptime = {
    date: string;
    /** Null for days before the service was first checked. */
    uptime: number | null;
    downtime_seconds: number;
    incidents: number;
};

export type ServiceDowntime = {
    uptime: Record<'24h' | '7d' | '30d' | '90d', number | null>;
    stats: {
        incidents: number;
        downtime_seconds: number;
        mean_time_to_recovery_seconds: number | null;
        longest_seconds: number | null;
    };
    daily: ServiceDailyUptime[];
};

export type Incident = {
    id: number;
    started_at: string;
    ended_at: string | null;
    duration_seconds: number;
    cause: string | null;
    status_code: number | null;
    failed_checks: number;
};

export type ServiceAlert =
    | {
          id: string;
          kind: 'down';
          at: string;
          ended_at: string | null;
          cause: string | null;
          status_code: number | null;
          checks: number;
      }
    | {
          id: string;
          kind: 'recovered';
          at: string;
          duration_seconds: number;
      }
    | {
          id: string;
          kind: 'slow';
          at: string;
          ended_at: string;
          checks: number;
          peak_latency_ms: number;
      };

export type ServiceAlerts = {
    events: ServiceAlert[];
    slow_threshold_ms: number;
    days: number;
};

/** A check run for live updates, broadcast over the websocket and never stored. */
export type LiveCheck = Omit<ServiceCheck, 'id'>;
