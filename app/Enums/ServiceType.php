<?php

namespace App\Enums;

enum ServiceType: string
{
    case Http = 'http';
    case Tcp = 'tcp';
    case Ssh = 'ssh';
    case Ping = 'ping';
    case Database = 'database';
    case Dns = 'dns';
    case Smtp = 'smtp';
    case InfluxDaemon = 'influx_daemon';

    /**
     * Get the human readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::Tcp => 'TCP port',
            self::Ssh => 'SSH',
            self::Ping => 'Ping (ICMP)',
            self::Database => 'Database',
            self::Dns => 'DNS',
            self::Smtp => 'SMTP',
            self::InfluxDaemon => 'Influx Daemon',
        };
    }

    /**
     * Determine whether services of this type connect to a port.
     */
    public function usesPort(): bool
    {
        return $this !== self::Ping;
    }

    /**
     * Determine whether services of this type are checked by the background collector.
     */
    public function collectsMetrics(): bool
    {
        return $this !== self::InfluxDaemon;
    }

    /**
     * Get every type the background collector can check.
     *
     * @return list<self>
     */
    public static function collectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->collectsMetrics()));
    }

    /**
     * Get the port conventionally used by this type, if any.
     */
    public function defaultPort(bool $useSsl = false): ?int
    {
        return match ($this) {
            self::Http => $useSsl ? 443 : 80,
            self::Tcp, self::Ping, self::InfluxDaemon => null,
            self::Ssh => 22,
            self::Database => 3306,
            self::Dns => 53,
            self::Smtp => $useSsl ? 465 : 25,
        };
    }

    /**
     * Get every type as options for the UI.
     *
     * @return list<array{value: string, label: string, uses_port: bool, default_port: int|null, default_ssl_port: int|null}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'uses_port' => $type->usesPort(),
            'default_port' => $type->defaultPort(),
            'default_ssl_port' => $type->defaultPort(useSsl: true),
        ], self::cases());
    }
}
