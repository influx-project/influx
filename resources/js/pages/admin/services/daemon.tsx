import { Head } from '@inertiajs/react';
import { ServiceDaemon } from '@/components/service-monitoring/daemon';
import { RangePicker } from '@/components/service-monitoring/range-picker';
import ServiceLayout from '@/layouts/service-layout';
import { adminServiceRoutes } from '@/lib/service-routes';
import type {
    Service,
    ServiceDaemon as Daemon,
    ServiceStatus,
    TimeRangeOption,
    TimeRangeValue,
} from '@/types';

export default function AdminServiceDaemonPage({
    service,
    status,
    daemon,
    range,
    ranges,
}: {
    service: Service;
    status: ServiceStatus;
    daemon: Daemon;
    range: TimeRangeValue;
    ranges: TimeRangeOption[];
}) {
    return (
        <>
            <Head title={`Daemon · ${service.name}`} />
            <ServiceLayout
                service={service}
                status={status}
                routes={adminServiceRoutes}
                tab="daemon"
                actions={
                    <RangePicker
                        value={range}
                        options={ranges}
                        only={['range', 'daemon', 'status']}
                    />
                }
            >
                <ServiceDaemon
                    service={service}
                    status={status}
                    daemon={daemon}
                    range={range}
                    ranges={ranges}
                    settingsHref={adminServiceRoutes.edit(service.id)}
                />
            </ServiceLayout>
        </>
    );
}
