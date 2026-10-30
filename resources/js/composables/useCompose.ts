import { reactive } from 'vue';
import { toast } from 'vue-sonner';
import { json } from '@/lib/http';
import { show as showDraft, store as storeDraft } from '@/routes/drafts';
import type { Draft, Recipient } from '@/types';

type StartOptions = {
    mailboxId: number;
    mode?: 'new' | 'reply' | 'reply_all' | 'forward';
    sourceId?: number;
    to?: Recipient[];
};

/** Satu panel tulis global (seperti Gmail) — bisa dibuka dari halaman mana pun. */
const state = reactive({
    open: false,
    minimized: false,
    loading: false,
    draft: null as Draft | null,
});

export function useCompose() {
    async function start(options: StartOptions) {
        if (
            state.open &&
            state.draft &&
            !confirm(
                'Tutup draf yang sedang ditulis? Draf tetap tersimpan di folder Draf.',
            )
        ) {
            return;
        }

        state.loading = true;

        try {
            state.draft = await json<Draft>('POST', storeDraft.url(), {
                mailbox_id: options.mailboxId,
                mode: options.mode ?? 'new',
                source_id: options.sourceId,
                to: options.to,
            });
            state.open = true;
            state.minimized = false;
        } catch (e) {
            toast.error((e as Error).message);
        } finally {
            state.loading = false;
        }
    }

    async function resume(draftId: number) {
        state.loading = true;

        try {
            state.draft = await json<Draft>('GET', showDraft.url(draftId));
            state.open = true;
            state.minimized = false;
        } catch (e) {
            toast.error((e as Error).message);
        } finally {
            state.loading = false;
        }
    }

    function close() {
        state.open = false;
        state.draft = null;
    }

    return { state, start, resume, close };
}
