export type ServiceTypeValue =
    | 'http'
    | 'tcp'
    | 'ssh'
    | 'ping'
    | 'database'
    | 'dns'
    | 'smtp';

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
