import { Head } from '@inertiajs/react';
import { ServiceOverview } from '@/components/service-monitoring/overview';
import { RangePicker } from '@/components/service-monitoring/range-picker';
import ServiceLayout from '@/layouts/service-layout';
import { adminServiceRoutes } from '@/lib/service-routes';
import type {
    Service,
    ServiceOverview as Overview,
    ServiceStatus,
    TimeRangeOption,
    TimeRangeValue,
} from '@/types';

export default function AdminShowService({
    service,
    status,
    overview,
    range,
    ranges,
}: {
    service: Service;
    status: ServiceStatus;
    overview: Overview;
    range: TimeRangeValue;
    ranges: TimeRangeOption[];
}) {
    return (
        <>
            <Head title={service.name} />
            <ServiceLayout
                service={service}
                status={status}
                routes={adminServiceRoutes}
                tab="show"
                actions={<RangePicker value={range} options={ranges} />}
            >
                <ServiceOverview
                    service={service}
                    status={status}
                    overview={overview}
                    range={range}
                    ranges={ranges}
                    settingsHref={adminServiceRoutes.edit(service.id)}
                />
            </ServiceLayout>
        </>
    );
}
