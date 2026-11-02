import { router, usePage } from '@inertiajs/vue3';
import { configureEcho, echo, echoIsConfigured } from '@laravel/echo-vue';
import { useDebounceFn } from '@vueuse/core';
import type { ChannelAuthorizationCallback } from 'pusher-js';
import { computed, onBeforeUnmount, watch } from 'vue';
import { toast } from 'vue-sonner';
import { json } from '@/lib/http';
import mailRoutes from '@/routes/mail';

type MailboxEvent = {
    kind: 'received' | 'status';
    mailbox_id: number;
    thread_id: number | null;
    from?: string;
    subject?: string;
    spam?: boolean;
};

/**
 * Pembaruan realtime lewat Reverb. Tiap mailbox yang boleh dibuka punya channel
 * privat sendiri; email masuk dan perubahan status kirim memuat ulang daftar
 * tanpa menunggu polling, dan email masuk memunculkan toast.
 */
export function useRealtime() {
    const page = usePage();
    const config = page.props.realtime;

    if (!config || typeof window === 'undefined') {
        return;
    }

    if (!echoIsConfigured()) {
        configureEcho({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            // Otorisasi channel privat memakai sesi + cookie XSRF, sama seperti fetch lain.
            authorizer: (channel: { name: string }) => ({
                authorize: (
                    socketId: string,
                    callback: ChannelAuthorizationCallback,
                ) => {
                    json<{ auth: string }>('POST', '/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name,
                    })
                        .then((data) => callback(null, data))
                        .catch((e: Error) => callback(e, null));
                },
            }),
        });
    }

    // Beberapa event beruntun (mis. terkirim lalu diterima) cukup satu kali muat ulang.
    const reload = useDebounceFn(
        () => router.reload({ only: ['mail', 'rows', 'thread'] }),
        400,
    );

    function onEvent(e: MailboxEvent) {
        void reload();

        const viewing =
            (page.props.thread as { id: number } | null | undefined)?.id ===
            e.thread_id;

        if (e.kind !== 'received' || e.spam || viewing) {
            return;
        }

        const mailboxes = page.props.mail?.mailboxes ?? [];
        const mailbox = mailboxes.find((m) => m.id === e.mailbox_id);
        const subject = e.subject || '(tanpa subjek)';

        toast(`Email baru dari ${e.from}`, {
            description:
                mailboxes.length > 1 && mailbox
                    ? `${mailbox.name}: ${subject}`
                    : subject,
            action: e.thread_id
                ? {
                      label: 'Buka',
                      onClick: () =>
                          router.visit(
                              mailRoutes.index.url({
                                  mailbox: e.mailbox_id,
                                  folder: 'inbox',
                                  thread: e.thread_id!,
                              }),
                          ),
                  }
                : undefined,
        });
    }

    let joined: number[] = [];
    const ids = computed(() =>
        (page.props.mail?.mailboxes ?? []).map((m) => m.id),
    );

    watch(
        () => ids.value.join(','),
        () => {
            const next = ids.value;

            joined
                .filter((id) => !next.includes(id))
                .forEach((id) => echo().leave(`mailbox.${id}`));
            next.filter((id) => !joined.includes(id)).forEach((id) =>
                echo()
                    .private(`mailbox.${id}`)
                    .listen('.mailbox.updated', onEvent),
            );
            joined = next;
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        joined.forEach((id) => echo().leave(`mailbox.${id}`));
        joined = [];
    });
}
