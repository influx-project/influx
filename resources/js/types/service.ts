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
 */
export type ServiceState = 'up' | 'down' | 'pending' | 'paused';

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

/** What an Influx Daemon reports about itself and the host it runs on. */
export type DaemonAgent = {
    version: string;
    hostname: string;
    os: string;
    kernel: string;
    arch: string;
    cpu_model: string;
    cpu_cores: number;
    memory_total_bytes: number;
    boot_time: string;
    sample_interval_seconds: number;
    /** Whether the daemon can see a container runtime on its host. */
    containers_available: boolean;
};

/** One bucket of a daemon's history; the figures are null where no samples were stored. */
export type DaemonSeriesPoint = {
    time: string;
    cpu_percent: number | null;
    cpu_percent_max: number | null;
    memory_percent: number | null;
    disk_read_bytes_per_second: number | null;
    disk_write_bytes_per_second: number | null;
    network_rx_bytes_per_second: number | null;
    network_tx_bytes_per_second: number | null;
    /** Null when there are no samples, or the daemon could not see a container runtime. */
    containers_running: number | null;
    containers_unhealthy: number | null;
    containers_total: number | null;
};

export type DaemonHistory = {
    stats: {
        samples: number;
        cpu_percent: number | null;
        cpu_percent_max: number | null;
        memory_percent: number | null;
        /** How full the fullest disk was in the latest sample. */
        disk_used_percent: number | null;
        network_rx_bytes: number;
        network_tx_bytes: number;
        containers_running: number | null;
        containers_total: number | null;
    };
    series: DaemonSeriesPoint[];
};

/** What the panel knows about a service's Influx Daemon. */
export type ServiceDaemon = {
    /** Null until the panel has reached the daemon. */
    agent: DaemonAgent | null;
    last_seen_at: string | null;
    history: DaemonHistory;
};

/** How to connect an Influx Daemon to the panel, for people who can change the service. */
export type DaemonConnection = {
    token: string;
    /** Where the panel expects to reach the daemon. */
    url: string;
};

export type DaemonDisk = {
    mount: string;
    device: string;
    filesystem: string;
    used_bytes: number;
    total_bytes: number;
};

export type DaemonContainerState =
    | 'created'
    | 'running'
    | 'paused'
    | 'restarting'
    | 'exited'
    | 'dead';

export type DaemonContainer = {
    id: string;
    name: string;
    image: string;
    state: DaemonContainerState;
    /** Null for containers without a health check. */
    health: 'starting' | 'healthy' | 'unhealthy' | null;
    started_at: string | null;
    restart_count: number;
    /** Resource use is null for containers that are not running. */
    cpu_percent: number | null;
    memory_used_bytes: number | null;
    memory_limit_bytes: number | null;
};

/** One sample of a daemon's host, as pulled from it and broadcast to live viewers. */
export type DaemonSample = {
    /** Numbers the samples of one daemon process, from 1. */
    seq: number;
    collected_at: string;
    uptime_seconds: number;
    cpu: {
        usage_percent: number;
        load_1: number;
        load_5: number;
        load_15: number;
    };
    memory: {
        used_bytes: number;
        total_bytes: number;
        swap_used_bytes: number;
        swap_total_bytes: number;
    };
    disks: DaemonDisk[];
    disk_io: {
        read_bytes_per_second: number;
        write_bytes_per_second: number;
    };
    network: {
        rx_bytes_per_second: number;
        tx_bytes_per_second: number;
    };
    /** Null when the daemon cannot see a container runtime. */
    containers: DaemonContainer[] | null;
};
