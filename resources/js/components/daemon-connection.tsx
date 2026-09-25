import { Form } from '@inertiajs/react';
import { Check, Copy, Eye, EyeOff, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import type { DaemonConnection } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const MASK = '•'.repeat(24);

/**
 * How to point an Influx Daemon at this service: its token, ready-made config, and a way
 * to replace the token.
 */
export function DaemonConnectionCard({
    connection,
    regenerateForm,
}: {
    connection: DaemonConnection;
    regenerateForm: RouteFormDefinition<'post'>;
}) {
    const [revealed, setRevealed] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const url = new URL(connection.url);
    const tls = url.protocol === 'https:';

    const config = (token: string) =>
        [
            `listen: "0.0.0.0:${url.port}"`,
            `token: "${token}"`,
            ...(tls
                ? [
                      '# This service connects over SSL, so serve HTTPS:',
                      'tls_cert_file: "/etc/influx-daemon/cert.pem"',
                      'tls_key_file: "/etc/influx-daemon/key.pem"',
                  ]
                : []),
        ].join('\n');

    const docker = (token: string) =>
        [
            'docker run -d --name influx-daemon --restart unless-stopped \\',
            '  --pid host --network host \\',
            '  -v /:/host:ro,rslave \\',
            '  -v /var/run/docker.sock:/var/run/docker.sock:ro \\',
            '  -e INFLUX_DAEMON_HOST_ROOT=/host \\',
            `  -e INFLUX_DAEMON_LISTEN=0.0.0.0:${url.port} \\`,
            `  -e INFLUX_DAEMON_TOKEN=${token} \\`,
            '  ghcr.io/influx-project/influx-daemon:latest',
        ].join('\n');

    const shown = revealed ? connection.token : MASK;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Influx Daemon connection</CardTitle>
                <CardDescription>
                    The panel pulls metrics from the daemon at{' '}
                    <span className="font-mono text-xs">{connection.url}</span>.
                    The daemon only answers requests that carry this token.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="daemon-token">Token</Label>
                    <div className="flex gap-2">
                        <Input
                            id="daemon-token"
                            readOnly
                            value={shown}
                            className="font-mono"
                            onFocus={(event) => event.currentTarget.select()}
                        />
                        <IconButton
                            label={revealed ? 'Hide token' : 'Show token'}
                            onClick={() => setRevealed(!revealed)}
                        >
                            {revealed ? <EyeOff /> : <Eye />}
                        </IconButton>
                        <CopyButton
                            label="Copy token"
                            text={connection.token}
                        />
                    </div>
                </div>

                <Snippet
                    title="/etc/influx-daemon/config.yaml"
                    shown={config(shown)}
                    copied={config(connection.token)}
                />
                <Snippet
                    title="Or run it with Docker"
                    shown={docker(shown)}
                    copied={docker(connection.token)}
                    hint={
                        <>
                            If Docker says <code>/</code> is not a shared or
                            slave mount, as on WSL2, remove <code>,rslave</code>
                            .
                        </>
                    }
                />

                <Dialog open={confirming} onOpenChange={setConfirming}>
                    <DialogTrigger asChild>
                        <Button variant="outline">
                            <RefreshCw />
                            Regenerate token
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Regenerate the token?</DialogTitle>
                        <DialogDescription>
                            The daemon will reject the panel until its config
                            has the new token, so this service will show as down
                            in the meantime.
                        </DialogDescription>
                        <Form
                            {...regenerateForm}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setConfirming(false)}
                        >
                            {({ processing }) => (
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Regenerate token
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}

function Snippet({
    title,
    shown,
    copied,
    hint,
}: {
    title: string;
    shown: string;
    copied: string;
    hint?: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-medium">{title}</p>
                <CopyButton label={`Copy ${title}`} text={copied} />
            </div>
            <pre className="overflow-x-auto rounded-md border bg-muted/50 p-3 font-mono text-xs leading-relaxed">
                {shown}
            </pre>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function CopyButton({ label, text }: { label: string; text: string }) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === text;

    return (
        <IconButton
            label={copied ? 'Copied' : label}
            onClick={() => void copy(text)}
        >
            {copied ? <Check /> : <Copy />}
        </IconButton>
    );
}

function IconButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={label}
                    onClick={onClick}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
