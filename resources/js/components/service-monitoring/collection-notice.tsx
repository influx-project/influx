import { CircleDashed, CirclePause } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { Service, ServiceStatus } from '@/types';

/**
 * Explains why a service has no fresh data, when it does not.
 */
export function CollectionNotice({
    service,
    status,
}: {
    service: Service;
    status: ServiceStatus;
}) {
    switch (status.state) {
        case 'paused':
            return (
                <Alert>
                    <CirclePause />
                    <AlertTitle>Checks are paused</AlertTitle>
                    <AlertDescription>
                        {service.enabled
                            ? 'Background collection is switched off in this service’s settings.'
                            : 'Monitoring is switched off in this service’s settings.'}{' '}
                        History from earlier checks is still shown.
                    </AlertDescription>
                </Alert>
            );
        case 'pending':
            return (
                <Alert>
                    <CircleDashed />
                    <AlertTitle>Waiting for the first check</AlertTitle>
                    <AlertDescription>
                        Checks run in the background via the scheduler and the
                        queue worker. Results will appear here shortly after
                        both are running.
                    </AlertDescription>
                </Alert>
            );
        default:
            return null;
    }
}
